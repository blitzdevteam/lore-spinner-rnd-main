<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\PlayableMoment;
use App\Models\SessionAdaptation;
use Illuminate\Support\Facades\DB;

/**
 * Validates and applies story repair patches.
 *
 * A patch may only replace an existing, non-empty text field with new text
 * that passes the prose bans. Structure is never changed: no new fields, no
 * removed fields, and no edits to the identifiers the runtime keys on.
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

    /** Field names the runtime matches on; changing their text breaks the wiring. */
    private const array PROTECTED_FIELDS = [
        'moment_key', 'choice_id', 'choice_ids', 'canonical_choice_id', 'label', 'path_label',
        'id', 'key', 'session_number', 'moment_order', 'anchor_event_id', 'marker', 'target_moment_key',
        'choice_type', 'status', 'version',
    ];

    public function __construct(private readonly BannedProseValidator $bans) {}

    /**
     * @param  list<array<string, mixed>>  $patches
     * @param  array<string, mixed>  $voiceProfile
     * @return array{accepted: list<array{target:string, key:string, path:string, old_value:string, new_value:string, reason:string}>, rejected: list<array{patch: array<string, mixed>, reason: string}>}
     */
    public function validate(SessionAdaptation $session, array $patches, array $voiceProfile, StoryRepairContext $context): array
    {
        $material = $context->material($session);
        $accepted = [];
        $rejected = [];
        $seen = [];

        foreach ($patches as $patch) {
            $target = (string) ($patch['target'] ?? '');
            $key = mb_trim((string) ($patch['key'] ?? ''));
            $path = mb_trim((string) ($patch['path'] ?? ''));
            $newValue = mb_trim((string) ($patch['new_value'] ?? ''));
            $document = $this->document($material, $target, $key);
            $current = $document === null ? null : data_get($document, $path);
            $leaf = (string) last(explode('.', $path));
            $identity = "{$target}|{$key}|{$path}";

            $failure = match (true) {
                ! in_array($target, self::TARGETS, true) => "Unknown target '{$target}'.",
                $document === null => $target === 'scene_card' ? "No scene card '{$key}' in this session." : "Target '{$target}' is empty for this session.",
                $path === '' || ! is_string($current) || mb_trim($current) === '' => "Path '{$path}' is not an existing text field.",
                in_array($leaf, self::PROTECTED_FIELDS, true) || $this->insideProtectedList($path) => "Path '{$path}' is an identifier and cannot be patched.",
                $newValue === '' => 'The replacement text is empty.',
                $newValue === mb_trim($current) => 'The replacement is identical to the stored text.',
                isset($seen[$identity]) => 'This field already has a patch.',
                default => $this->banFailure($newValue, $voiceProfile),
            };

            if ($failure !== null) {
                $rejected[] = ['patch' => $patch, 'reason' => $failure];

                continue;
            }

            $seen[$identity] = true;
            $accepted[] = [
                'target' => $target,
                'key' => $key,
                'path' => $path,
                'old_value' => (string) $current,
                'new_value' => $newValue,
                'reason' => (string) ($patch['reason'] ?? ''),
            ];
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /**
     * @param  list<array{target:string, key:string, path:string, old_value:string, new_value:string, reason:string}>  $accepted
     */
    public function apply(SessionAdaptation $session, array $accepted, StoryRepairContext $context): void
    {
        DB::transaction(function () use ($session, $accepted, $context): void {
            $moments = $context->moments($session)->keyBy('moment_key');
            $columns = [];
            $cards = [];

            foreach ($accepted as $patch) {
                if ($patch['target'] === 'scene_card') {
                    /** @var PlayableMoment $moment */
                    $moment = $moments[$patch['key']];
                    $card = $cards[$patch['key']] ?? (array) $moment->scene_card;
                    data_set($card, $patch['path'], $patch['new_value']);
                    $cards[$patch['key']] = $card;

                    continue;
                }

                $column = $columns[$patch['target']] ?? (array) ($session->getAttribute($patch['target']) ?? []);
                data_set($column, $patch['path'], $patch['new_value']);
                $columns[$patch['target']] = $column;
            }

            foreach ($cards as $momentKey => $card) {
                $moments[$momentKey]->update(['scene_card' => $card]);
            }

            if ($columns !== []) {
                $session->update($columns);
            }
        });
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
}
