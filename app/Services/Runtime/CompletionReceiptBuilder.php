<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use App\Models\Game;
use App\Models\SessionAdaptation;
use App\Models\StoryAdaptation;

/**
 * Compiles a player-facing completion ceremony from runtime state that already
 * exists on the game and its adaptation. Never exposes raw alignment axes,
 * footnotes, or the full world-state blob.
 */
final class CompletionReceiptBuilder
{
    private const MAX_LINES = 3;

    /**
     * @return array{
     *     mode: 'session'|'story',
     *     story_title: string,
     *     session_number: int,
     *     final_line?: string,
     *     world_remembers?: list<string>,
     *     becoming?: array{identity: string, style_line: string|null},
     *     commitment?: string,
     *     what_waits?: array{next_opens: string, unresolved_promise: string|null},
     *     what_settled?: list<string>,
     *     record?: list<string>,
     *     became?: array{identity: string, style_line: string|null},
     *     leaves_behind?: string,
     *     share_line?: string
     * }|null
     */
    public function build(Game $game): ?array
    {
        if (! $game->current_session_complete) {
            return null;
        }

        $story = $game->relationLoaded('story') ? $game->story : null;
        $adaptation = $story !== null && $story->relationLoaded('adaptation')
            ? $story->adaptation
            : null;
        $sessionNumber = (int) ($game->current_session_number ?? 1);
        $totalSessions = (int) ($adaptation?->session_adaptations_count ?? 0);
        $isStoryEnd = $totalSessions > 0 && $sessionNumber >= $totalSessions;
        $closeDesign = $this->currentCloseDesign($adaptation, $sessionNumber);
        $worldState = is_array($game->world_state) ? $game->world_state : [];
        $chosenOption = $this->chosenCloseOption($game, $closeDesign);

        $receipt = [
            'mode' => $isStoryEnd ? 'story' : 'session',
            'story_title' => (string) ($story?->title ?? ''),
            'session_number' => $sessionNumber,
        ];

        $finalLine = $this->safeLine((string) ($closeDesign['session_end_choice']['final_line'] ?? ''))
            ?? $this->lastNarrationBeat($game);

        if ($finalLine !== null) {
            $receipt['final_line'] = $finalLine;
        }

        $identity = $this->storyNativeIdentity($game, $adaptation);
        $styleLine = $this->lastSafeLine($this->stringList($worldState['player_style'] ?? []));
        $becoming = $this->identityModule($identity, $styleLine);

        $shareLine = $this->safeLine((string) ($game->defining_choice_line ?? ''));

        if ($isStoryEnd) {
            $settled = $this->takeSafeLines($this->sentences((string) ($closeDesign['resolution_prose'] ?? '')));
            if ($settled !== []) {
                $receipt['what_settled'] = $settled;
            }

            $record = $this->recordLines($worldState);
            if ($record !== []) {
                $receipt['record'] = $record;
            }

            if ($becoming !== null) {
                $receipt['became'] = $becoming;
            }

            $leavesBehind = $this->leavesBehind($game, $closeDesign);
            if ($leavesBehind !== null) {
                $receipt['leaves_behind'] = $leavesBehind;
            }
        } else {
            $remembers = $this->rememberLines($worldState, $game);
            if ($remembers !== []) {
                $receipt['world_remembers'] = $remembers;
            }

            if ($becoming !== null) {
                $receipt['becoming'] = $becoming;
            }

            $commitment = $this->safeLine((string) ($chosenOption['text'] ?? ''));
            if ($commitment !== null) {
                $receipt['commitment'] = $commitment;
            }

            $whatWaits = $this->whatWaits($chosenOption, $closeDesign, $worldState);
            if ($whatWaits !== null) {
                $receipt['what_waits'] = $whatWaits;
            }

            $shareLine ??= $this->safeLine($remembers[0] ?? '');
        }

        $shareLine ??= $this->safeLine((string) ($receipt['record'][0] ?? $receipt['what_settled'][0] ?? ''));

        if ($shareLine !== null) {
            $receipt['share_line'] = $shareLine;
        }

        return $receipt;
    }

    /**
     * Load the adaptation and prompt relations the ceremony compile needs.
     */
    public function hydrate(Game $game): Game
    {
        $currentSessionNumber = (int) ($game->current_session_number ?? 1);

        $game->load([
            'story' => fn ($q) => $q->with([
                'adaptation' => fn ($a) => $a
                    ->withCount('sessionAdaptations')
                    ->with(['sessionAdaptations' => fn ($sa) => $sa
                        ->whereIn('session_number', array_values(array_unique([1, $currentSessionNumber])))
                        ->select([
                            'id',
                            'story_adaptation_id',
                            'session_number',
                            'entry_point_diagnosis',
                            'session_close_design',
                            'session_choice_design',
                        ]),
                    ]),
            ]),
            'prompts' => fn ($q) => $q
                ->select(['id', 'game_id', 'session_number', 'response', 'choices', 'prompt'])
                ->orderBy('id'),
        ]);

        return $game;
    }

    /**
     * @param  array<string, mixed>|null  $closeDesign
     * @return array<string, mixed>
     */
    private function currentCloseDesign(?StoryAdaptation $adaptation, int $sessionNumber): array
    {
        if ($adaptation === null || ! $adaptation->relationLoaded('sessionAdaptations')) {
            return [];
        }

        $session = $adaptation->sessionAdaptations->firstWhere('session_number', $sessionNumber);

        return $session instanceof SessionAdaptation && is_array($session->session_close_design)
            ? $session->session_close_design
            : [];
    }

    /**
     * @param  array<string, mixed>  $closeDesign
     * @return array<string, mixed>|null
     */
    private function chosenCloseOption(Game $game, array $closeDesign): ?array
    {
        $path = mb_strtoupper(mb_trim((string) ($game->defining_path_label ?? '')));
        $optionKey = match ($path) {
            'A' => 'option_a',
            'B' => 'option_b',
            'C' => 'option_c',
            default => null,
        };

        if ($optionKey === null) {
            return null;
        }

        $option = $closeDesign['session_end_choice'][$optionKey] ?? null;

        return is_array($option) ? $option : null;
    }

    /**
     * Dominant scaffold axis → story-native label. Mirrors ChaosEngineService::renderAlignmentTilt()
     * threshold rules, but returns the player-facing name instead of a hidden narrator instruction.
     */
    private function storyNativeIdentity(Game $game, ?StoryAdaptation $adaptation): ?string
    {
        $labels = is_array($adaptation?->story_session_map)
            ? ($adaptation->story_session_map['alignment_labels'] ?? [])
            : [];

        if (! is_array($labels) || $labels === []) {
            return null;
        }

        $scaffold = is_array($game->alignment_scaffold) ? $game->alignment_scaffold : [];
        $chaotic = (int) ($scaffold['chaotic'] ?? 0);
        $lawful = (int) ($scaffold['lawful'] ?? 0);
        $neutral = (int) ($scaffold['neutral'] ?? 0);
        $total = $chaotic + $lawful + $neutral;

        if ($total === 0) {
            return null;
        }

        $threshold = (int) ceil($total * 0.4);
        $top = max($chaotic, $lawful, $neutral);
        $tieCount = (int) ($chaotic === $top) + (int) ($lawful === $top) + (int) ($neutral === $top);

        if ($tieCount !== 1 || $top < $threshold) {
            return null;
        }

        $dominant = match (true) {
            $chaotic === $top => 'chaotic',
            $lawful === $top => 'lawful',
            default => 'neutral',
        };

        foreach ($labels as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mapsTo = mb_strtolower((string) ($row['maps_to_internal'] ?? ''));

            if ($mapsTo !== $dominant) {
                continue;
            }

            return $this->safeLine((string) ($row['label'] ?? ''));
        }

        return null;
    }

    /**
     * @return array{identity: string, style_line: string|null}|null
     */
    private function identityModule(?string $identity, ?string $styleLine): ?array
    {
        if ($identity === null) {
            return null;
        }

        return [
            'identity' => $identity,
            'style_line' => $styleLine,
        ];
    }

    /**
     * @param  array<string, mixed>  $worldState
     * @return list<string>
     */
    private function rememberLines(array $worldState, Game $game): array
    {
        $lines = [
            ...array_slice($this->ledgerEntries($worldState), -self::MAX_LINES),
            ...$this->stringList($worldState['relationship_updates'] ?? []),
            ...$this->stringList($worldState['knowledge'] ?? []),
        ];

        $defining = $this->safeLine((string) ($game->defining_choice_line ?? ''));
        if ($defining !== null) {
            $lines[] = $defining;
        }

        return $this->takeSafeLines($lines);
    }

    /**
     * @param  array<string, mixed>  $worldState
     * @return list<string>
     */
    private function recordLines(array $worldState): array
    {
        $lines = [
            ...array_slice($this->ledgerEntries($worldState), -self::MAX_LINES),
            ...$this->stringList($worldState['knowledge'] ?? []),
        ];

        return $this->takeSafeLines($lines);
    }

    /**
     * @param  array<string, mixed>|null  $chosenOption
     * @param  array<string, mixed>  $closeDesign
     * @param  array<string, mixed>  $worldState
     * @return array{next_opens: string, unresolved_promise: string|null}|null
     */
    private function whatWaits(?array $chosenOption, array $closeDesign, array $worldState): ?array
    {
        $nextOpens = $this->safeLine((string) ($chosenOption['next_session_opens'] ?? ''))
            ?? $this->safeLine((string) ($closeDesign['hook_transition'] ?? ''));

        if ($nextOpens === null) {
            return null;
        }

        $unresolved = null;

        foreach ($this->stringList($worldState['unresolved_promises'] ?? []) as $promise) {
            $unresolved = $this->safeLine($promise);

            if ($unresolved !== null) {
                break;
            }
        }

        return [
            'next_opens' => $nextOpens,
            'unresolved_promise' => $unresolved,
        ];
    }

    /**
     * @param  array<string, mixed>  $closeDesign
     */
    private function leavesBehind(Game $game, array $closeDesign): ?string
    {
        $memory = mb_trim((string) ($game->symbolic_memory ?? ''));
        if ($memory !== '') {
            $paragraphs = preg_split("/\n\s*\n/", $memory) ?: [$memory];
            $lastParagraph = mb_trim((string) array_pop($paragraphs));
            $fromMemory = $this->safeLine($lastParagraph);
            if ($fromMemory !== null) {
                return $fromMemory;
            }
        }

        $sentences = $this->sentences((string) ($closeDesign['resolution_prose'] ?? ''));

        return $this->lastSafeLine($sentences);
    }

    private function lastNarrationBeat(Game $game): ?string
    {
        if (! $game->relationLoaded('prompts')) {
            return null;
        }

        $last = $game->prompts->sortBy('id')->last();
        $plain = $this->plainText((string) ($last?->response ?? ''));
        $sentences = $this->sentences($plain);

        return $this->lastSafeLine($sentences) ?? $this->safeLine($plain);
    }

    /**
     * @param  array<string, mixed>  $worldState
     * @return list<string>
     */
    private function ledgerEntries(array $worldState): array
    {
        $entries = [];

        foreach ((array) ($worldState['emotional_ledger'] ?? []) as $row) {
            if (is_string($row)) {
                $entries[] = $row;

                continue;
            }

            if (is_array($row) && isset($row['entry'])) {
                $entries[] = (string) $row['entry'];
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $lines = [];

        foreach ($value as $key => $item) {
            if (is_string($item) && mb_trim($item) !== '') {
                $lines[] = is_int($key) || str_contains($item, (string) $key)
                    ? $item
                    : $key.': '.$item;

                continue;
            }

            if (is_array($item) && isset($item['entry']) && is_string($item['entry'])) {
                $lines[] = $item['entry'];
            }
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function takeSafeLines(array $lines): array
    {
        $safe = [];

        foreach ($lines as $line) {
            $cleaned = $this->safeLine($line);

            if ($cleaned === null || in_array($cleaned, $safe, true)) {
                continue;
            }

            $safe[] = $cleaned;

            if (count($safe) >= self::MAX_LINES) {
                break;
            }
        }

        return $safe;
    }

    /**
     * @return list<string>
     */
    private function sentences(string $prose): array
    {
        $plain = $this->plainText($prose);

        if ($plain === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?])\s+/u', $plain) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $part): string => mb_trim($part), $parts),
            static fn (string $part): bool => $part !== '',
        ));
    }

    /**
     * @param  list<string>  $lines
     */
    private function lastSafeLine(array $lines): ?string
    {
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $safe = $this->safeLine($lines[$index]);

            if ($safe !== null) {
                return $safe;
            }
        }

        return null;
    }

    private function safeLine(string $text): ?string
    {
        $cleaned = $this->plainText($text);

        if ($cleaned === '') {
            return null;
        }

        if (preg_match('/\b(chaotic|lawful|neutral)\b/iu', $cleaned) === 1) {
            return null;
        }

        return $cleaned;
    }

    private function plainText(string $text): string
    {
        $stripped = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');
    }
}
