<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\PlayableMoment;
use App\Models\SessionAdaptation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates and applies story repair patches and rebinds.
 *
 * A patch may only replace an existing, non-empty text field with new text
 * that passes the prose bans. A rebind moves one authored choice from one
 * card to another and updates every record that names its card, so the
 * scene cards, choice design, consequence map and final cut never disagree.
 * Everything a change touches is snapshotted first so it can be reverted.
 */
final class StoryRepairPatchApplier
{
    public const array TARGETS = [
        'scene_card',
        'session_choice_design',
        'choice_consequence_map',
        'session_close_design',
        'entry_point_diagnosis',
    ];

    /** Columns a repair may write, snapshotted whole before any change. */
    private const array COLUMNS = [
        'session_choice_design',
        'choice_consequence_map',
        'session_close_design',
        'entry_point_diagnosis',
        'boiler_final_cut',
    ];

    /** Field names the runtime matches on; changing their text breaks the wiring. */
    private const array PROTECTED_FIELDS = [
        'moment_key', 'choice_id', 'choice_ids', 'canonical_choice_id', 'label', 'path_label',
        'id', 'key', 'session_number', 'moment_order', 'anchor_event_id', 'marker', 'target_moment_key',
        'choice_type', 'status', 'version', 'closing_moment_key', 'next_moment_key',
    ];

    public function __construct(
        private readonly BannedProseValidator $bans,
        private readonly ColdOpenRepairValidator $coldOpenValidator,
        private readonly StoryRepairContext $context,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $patches
     * @param  list<array<string, mixed>>  $rebinds
     * @param  array{protagonist: string, voice_profile: array<string, mixed>}  $context
     * @param  list<string>  $skipTargets
     * @param  list<string>  $reorder  The complete new card order, or empty for none.
     * @return array{
     *     patches: list<array{target:string, key:string, path:string, old_value:string, new_value:string, reason:string}>,
     *     rebinds: list<array{choice_id:string, from_moment_key:string, to_moment_key:string, reason:string}>,
     *     reorder: list<string>,
     *     rejected: list<array{change: array<string, mixed>, reason: string}>
     * }
     */
    public function validate(SessionAdaptation $session, array $patches, array $rebinds, array $context, array $skipTargets = [], array $reorder = []): array
    {
        $material = $this->context->material($session);
        $rejected = [];

        [$acceptedRebinds, $rebindRejections] = $this->validateRebinds($session, $material, $rebinds, $skipTargets);
        array_push($rejected, ...$rebindRejections);

        $acceptedPatches = [];
        $seen = [];

        foreach ($patches as $patch) {
            $target = (string) ($patch['target'] ?? '');
            $key = mb_trim((string) ($patch['key'] ?? ''));
            $path = mb_trim((string) ($patch['path'] ?? ''));
            $newValue = BannedProseValidator::neutralizeBannedPunctuation(mb_trim((string) ($patch['new_value'] ?? '')));
            $document = $this->document($material, $target, $key);
            $current = $document === null ? null : data_get($document, $path);
            $leaf = (string) last(explode('.', $path));
            $identity = "{$target}|{$key}|{$path}";
            $reviewedValue = $patch['old_value'] ?? null;

            $failure = match (true) {
                ! in_array($target, self::TARGETS, true) => "Unknown target '{$target}'.",
                in_array($target, $skipTargets, true) => "Target '{$target}' is skipped for this run.",
                $document === null => $target === 'scene_card' ? "No scene card '{$key}' in this session." : "Target '{$target}' is empty for this session.",
                $path === '' || ! is_string($current) || mb_trim($current) === '' => "Path '{$path}' is not an existing text field.",
                in_array($leaf, self::PROTECTED_FIELDS, true) || $this->insideProtectedList($path) => "Path '{$path}' is an identifier and cannot be patched.",
                is_string($reviewedValue) && mb_trim($reviewedValue) !== mb_trim($current) => "Path '{$path}' changed since it was reviewed.",
                $newValue === '' => 'The replacement text is empty.',
                $newValue === mb_trim($current) => 'The replacement is identical to the stored text.',
                isset($seen[$identity]) => 'This field already has a patch.',
                default => $this->banFailure($newValue, $context['voice_profile'])
                    ?? ($target === 'entry_point_diagnosis' && $path === 'cold_open' ? $this->coldOpenFailure($newValue, $context['protagonist']) : null),
            };

            if ($failure !== null) {
                $rejected[] = ['change' => $patch, 'reason' => $failure];

                continue;
            }

            $seen[$identity] = true;
            $acceptedPatches[] = [
                'target' => $target,
                'key' => $key,
                'path' => $path,
                'old_value' => (string) $current,
                'new_value' => $newValue,
                'reason' => (string) ($patch['reason'] ?? ''),
            ];
        }

        [$acceptedReorder, $reorderFailure] = $this->validateReorder($session, $material, $reorder, $acceptedPatches, $skipTargets);
        if ($reorderFailure !== null) {
            $rejected[] = ['change' => ['reorder' => $reorder], 'reason' => $reorderFailure];
        }

        return ['patches' => $acceptedPatches, 'rebinds' => $acceptedRebinds, 'reorder' => $acceptedReorder, 'rejected' => $rejected];
    }

    /**
     * Apply accepted changes and return the snapshot needed to undo them.
     *
     * @param  list<array{target:string, key:string, path:string, old_value:string, new_value:string, reason:string}>  $patches
     * @param  list<array{choice_id:string, from_moment_key:string, to_moment_key:string, reason:string}>  $rebinds
     * @param  list<string>  $reorder
     * @return array{scene_cards: array<string, array<string, mixed>>, moment_orders: array<string, int>, columns: array<string, mixed>}
     */
    public function apply(SessionAdaptation $session, array $patches, array $rebinds, array $reorder = []): array
    {
        return DB::transaction(function () use ($session, $patches, $rebinds, $reorder): array {
            $session->refresh();
            $moments = $this->context->moments($session)->keyBy('moment_key');
            $backup = [
                'scene_cards' => $moments->map(fn (PlayableMoment $moment): array => (array) $moment->scene_card)->all(),
                'moment_orders' => $moments->map(fn (PlayableMoment $moment): int => (int) $moment->moment_order)->all(),
                'columns' => collect(self::COLUMNS)->mapWithKeys(fn (string $column): array => [$column => $session->getAttribute($column)])->all(),
            ];

            $cards = $backup['scene_cards'];
            $columns = array_map(static fn ($value): array => (array) ($value ?? []), $backup['columns']);

            if ($reorder !== []) {
                $this->reorder($moments, $cards, $columns, $reorder);
            }

            foreach ($rebinds as $rebind) {
                $this->rebind($cards, $columns, $rebind);
            }

            foreach ($patches as $patch) {
                if ($patch['target'] === 'scene_card') {
                    data_set($cards[$patch['key']], $patch['path'], $patch['new_value']);
                    $this->mirrorFinalCutCard($columns, $patch['key'], $cards[$patch['key']]);

                    continue;
                }

                data_set($columns[$patch['target']], $patch['path'], $patch['new_value']);
            }

            $this->persist($session, $moments, $cards, $columns, $backup);

            return $backup;
        });
    }

    /**
     * @param  array{scene_cards: array<string, array<string, mixed>>, moment_orders?: array<string, int>, columns: array<string, mixed>}  $backup
     */
    public function revert(SessionAdaptation $session, array $backup): void
    {
        DB::transaction(function () use ($session, $backup): void {
            $moments = $this->context->moments($session)->keyBy('moment_key');

            foreach ((array) ($backup['moment_orders'] ?? []) as $momentKey => $order) {
                $moments->get($momentKey)?->update(['moment_order' => $order]);
            }

            foreach ($backup['scene_cards'] as $momentKey => $card) {
                $moments->get($momentKey)?->update(['scene_card' => $card]);
            }

            $session->update($backup['columns']);
        });
    }

    /**
     * A reorder must keep every card exactly once, keep the closing card last,
     * and arrive with the text that depends on order already rewritten: the
     * bridge out of every card whose next card changes, and the cold open when
     * the first card changes. Without those the cards would hand into the
     * wrong scene.
     *
     * @param  array<string, mixed>  $material
     * @param  list<string>  $reorder
     * @param  list<array{target:string, key:string, path:string, old_value:string, new_value:string, reason:string}>  $acceptedPatches
     * @param  list<string>  $skipTargets
     * @return array{0: list<string>, 1: ?string}
     */
    private function validateReorder(SessionAdaptation $session, array $material, array $reorder, array $acceptedPatches, array $skipTargets): array
    {
        $order = array_values(array_map(static fn ($key): string => mb_trim((string) $key), $reorder));

        if ($order === []) {
            return [[], null];
        }

        $current = $this->context->moments($session)->pluck('moment_key')->all();
        $closingKey = (string) (data_get($session->session_close_design, 'closing_moment_key') ?: last($current));
        $patched = collect($acceptedPatches)->map(fn (array $patch): string => "{$patch['target']}|{$patch['key']}|{$patch['path']}")->all();

        $failure = match (true) {
            in_array('scene_card', $skipTargets, true) => 'Reorder is skipped for this run.',
            count($order) !== count(array_unique($order)) => 'The new order lists a card more than once.',
            array_diff($current, $order) !== [] => 'The new order leaves out '.implode(', ', array_diff($current, $order)).'.',
            array_diff($order, $current) !== [] => 'The new order names unknown cards '.implode(', ', array_diff($order, $current)).'.',
            $order === $current => 'The new order is the current order.',
            last($order) !== $closingKey => "The closing card {$closingKey} must stay last.",
            default => null,
        };

        if ($failure !== null) {
            return [[], $failure];
        }

        $missingBridges = [];
        foreach ($order as $index => $momentKey) {
            $oldIndex = array_search($momentKey, $current, true);
            $oldNext = $current[$oldIndex + 1] ?? null;
            $newNext = $order[$index + 1] ?? null;
            $bridge = mb_trim((string) data_get($material, "scene_cards.{$momentKey}.next_bridge", ''));

            if ($oldNext !== $newNext && $bridge !== '' && ! in_array("scene_card|{$momentKey}|next_bridge", $patched, true)) {
                $missingBridges[] = $momentKey;
            }
        }

        if ($missingBridges !== []) {
            return [[], 'The new order changes where these cards lead, but their next_bridge was not rewritten: '.implode(', ', $missingBridges).'.'];
        }

        $coldOpen = mb_trim((string) data_get($material, 'entry_point_diagnosis.cold_open', ''));
        if ($order[0] !== $current[0] && $coldOpen !== '' && ! in_array('entry_point_diagnosis||cold_open', $patched, true)) {
            return [[], "The new order starts on {$order[0]}, but no valid cold open for that start was accepted."];
        }

        return [$order, null];
    }

    /**
     * @param  array<string, mixed>  $material
     * @param  list<array<string, mixed>>  $rebinds
     * @param  list<string>  $skipTargets
     * @return array{0: list<array{choice_id:string, from_moment_key:string, to_moment_key:string, reason:string}>, 1: list<array{change: array<string, mixed>, reason: string}>}
     */
    private function validateRebinds(SessionAdaptation $session, array $material, array $rebinds, array $skipTargets): array
    {
        $cards = (array) $material['scene_cards'];
        $authoredIds = collect(['branching_choices', 'emotional_choices'])
            ->flatMap(fn (string $bucket): array => (array) data_get($material, "session_choice_design.{$bucket}", []))
            ->map(fn ($choice): string => (string) data_get($choice, 'choice_id', ''))
            ->filter()
            ->values()
            ->all();
        $closingKey = (string) (data_get($session->session_close_design, 'closing_moment_key') ?: array_key_last($cards));
        $accepted = [];
        $rejected = [];
        $moved = [];

        foreach ($rebinds as $rebind) {
            $choiceId = mb_trim((string) ($rebind['choice_id'] ?? ''));
            $from = mb_trim((string) ($rebind['from_moment_key'] ?? ''));
            $to = mb_trim((string) ($rebind['to_moment_key'] ?? ''));

            $failure = match (true) {
                in_array('scene_card', $skipTargets, true) || in_array('session_choice_design', $skipTargets, true) => 'Rebinds are skipped for this run.',
                ! in_array($choiceId, $authoredIds, true) => "Choice '{$choiceId}' is not authored in this session.",
                ! isset($cards[$from]) || ! isset($cards[$to]) => "Unknown card '".(! isset($cards[$from]) ? $from : $to)."'.",
                $from === $closingKey => "Card '{$from}' closes the session; its ending choice stays there.",
                $from === $to => 'The choice is already on that card.',
                ! in_array($choiceId, (array) ($cards[$from]['choice_ids'] ?? []), true) => "Choice '{$choiceId}' is not on card '{$from}'.",
                isset($moved[$choiceId]) => "Choice '{$choiceId}' already has a rebind.",
                default => null,
            };

            if ($failure !== null) {
                $rejected[] = ['change' => $rebind, 'reason' => $failure];

                continue;
            }

            $moved[$choiceId] = true;
            $accepted[] = [
                'choice_id' => $choiceId,
                'from_moment_key' => $from,
                'to_moment_key' => $to,
                'reason' => (string) ($rebind['reason'] ?? ''),
            ];
        }

        return [$accepted, $rejected];
    }

    /**
     * Renumber the cards 1..N in the new order and relink next_moment_key, in
     * the playable moments and in the final cut, so the runtime's
     * moment_order + 1 walk follows the new sequence exactly.
     *
     * @param  Collection<string, PlayableMoment>  $moments
     * @param  array<string, array<string, mixed>>  $cards
     * @param  array<string, array<string, mixed>>  $columns
     * @param  list<string>  $order
     */
    private function reorder(Collection $moments, array &$cards, array &$columns, array $order): void
    {
        foreach ($order as $index => $momentKey) {
            $moments[$momentKey]->update(['moment_order' => $index + 1]);
            $next = $order[$index + 1] ?? null;

            if ($next === null) {
                unset($cards[$momentKey]['next_moment_key']);
            } else {
                $cards[$momentKey]['next_moment_key'] = $next;
            }

            if (array_key_exists('moment_order', $cards[$momentKey])) {
                $cards[$momentKey]['moment_order'] = $index + 1;
            }
        }

        $finalMoments = collect((array) ($columns['boiler_final_cut']['moments'] ?? []))->keyBy(fn ($card): string => (string) data_get($card, 'moment_key'));
        if ($finalMoments->isNotEmpty()) {
            $columns['boiler_final_cut']['moments'] = collect($order)
                ->filter(fn (string $momentKey): bool => $finalMoments->has($momentKey))
                ->map(function (string $momentKey, int $index) use ($finalMoments, $order): array {
                    $card = (array) $finalMoments[$momentKey];
                    $card['moment_order'] = $index + 1;
                    $card['next_moment_key'] = $order[$index + 1] ?? '';

                    return $card;
                })
                ->values()
                ->all();
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $cards
     * @param  array<string, array<string, mixed>>  $columns
     * @param  array{choice_id:string, from_moment_key:string, to_moment_key:string, reason:string}  $rebind
     */
    private function rebind(array &$cards, array &$columns, array $rebind): void
    {
        $choiceId = $rebind['choice_id'];
        $cards[$rebind['from_moment_key']]['choice_ids'] = array_values(array_filter(
            (array) ($cards[$rebind['from_moment_key']]['choice_ids'] ?? []),
            static fn ($id): bool => $id !== $choiceId,
        ));
        $cards[$rebind['to_moment_key']]['choice_ids'] = array_values(array_unique([
            ...(array) ($cards[$rebind['to_moment_key']]['choice_ids'] ?? []),
            $choiceId,
        ]));

        foreach (['session_choice_design' => ['branching_choices', 'emotional_choices'], 'choice_consequence_map' => ['branching_consequences', 'emotional_consequences']] as $column => $buckets) {
            foreach ($buckets as $bucket) {
                foreach ((array) ($columns[$column][$bucket] ?? []) as $index => $entry) {
                    if (is_array($entry) && ($entry['choice_id'] ?? null) === $choiceId && array_key_exists('moment_key', $entry)) {
                        $columns[$column][$bucket][$index]['moment_key'] = $rebind['to_moment_key'];
                    }
                }
            }
        }

        $this->mirrorFinalCutCard($columns, $rebind['from_moment_key'], $cards[$rebind['from_moment_key']]);
        $this->mirrorFinalCutCard($columns, $rebind['to_moment_key'], $cards[$rebind['to_moment_key']]);
    }

    /**
     * The finalized cut is the source the playable moments were normalized from;
     * it is kept identical so a later normalize cannot bring a defect back.
     *
     * @param  array<string, array<string, mixed>>  $columns
     * @param  array<string, mixed>  $card
     */
    private function mirrorFinalCutCard(array &$columns, string $momentKey, array $card): void
    {
        foreach ((array) ($columns['boiler_final_cut']['moments'] ?? []) as $index => $finalCard) {
            if (is_array($finalCard) && ($finalCard['moment_key'] ?? null) === $momentKey) {
                $columns['boiler_final_cut']['moments'][$index] = [
                    ...$finalCard,
                    ...$card,
                    ...array_intersect_key($finalCard, array_flip(['moment_order', 'next_moment_key'])),
                ];
            }
        }
    }

    /**
     * @param  Collection<string, PlayableMoment>  $moments
     * @param  array<string, array<string, mixed>>  $cards
     * @param  array<string, array<string, mixed>>  $columns
     * @param  array{scene_cards: array<string, array<string, mixed>>, columns: array<string, mixed>}  $backup
     */
    private function persist(SessionAdaptation $session, Collection $moments, array $cards, array $columns, array $backup): void
    {
        foreach ($cards as $momentKey => $card) {
            if ($card !== $backup['scene_cards'][$momentKey]) {
                $moments[$momentKey]->update(['scene_card' => $card]);
            }
        }

        $changed = [];
        foreach ($columns as $column => $value) {
            if ($value !== (array) ($backup['columns'][$column] ?? [])) {
                $changed[$column] = $value;
            }
        }

        if ($changed !== []) {
            $session->update($changed);
        }
    }

    /**
     * @param  array<string, mixed>  $material
     * @return array<string, mixed>|null
     */
    private function document(array $material, string $target, string $key): ?array
    {
        $document = $target === 'scene_card'
            ? ($material['scene_cards'][$key] ?? null)
            : ($material[$target] ?? null);

        return is_array($document) && $document !== [] ? $document : null;
    }

    private function insideProtectedList(string $path): bool
    {
        $segments = explode('.', $path);
        array_pop($segments);

        return array_intersect($segments, ['choice_ids']) !== [];
    }

    /**
     * @param  array<string, mixed>  $voiceProfile
     */
    private function banFailure(string $text, array $voiceProfile): ?string
    {
        $errors = $this->bans->scan(['value' => $text], $voiceProfile, ['value'])['errors'];

        return $errors === [] ? null : 'Replacement breaks the prose bans: '.implode(' ', $errors);
    }

    private function coldOpenFailure(string $prose, string $protagonist): ?string
    {
        $failures = $this->coldOpenValidator->failures($prose, $protagonist);

        return $failures === [] ? null : 'Cold open breaks the cold-open contract: '.implode(' ', $failures);
    }
}
