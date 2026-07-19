<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

final class SessionArchitectureCanonicalizer
{
    /**
     * Bind Architecture branching slots to Draft CHOICE-role moments.
     *
     * The Draft decides where load-bearing choice pressure exists. Architecture
     * may describe and time the slot, but it cannot place all four canonical
     * branches elsewhere.
     *
     * @param  array<string, mixed>  $architecture
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function canonicalize(array $architecture, array $draft): array
    {
        $rolesByMoment = collect((array) ($draft['event_role_map'] ?? []))
            ->filter(fn ($role): bool => is_array($role)
                && ($role['role'] ?? null) === 'CHOICE'
                && is_string($role['included_moment_key'] ?? null)
                && $role['included_moment_key'] !== '')
            ->groupBy('included_moment_key');
        $draftMoments = collect((array) ($draft['moment_drafts'] ?? []))
            ->keyBy('moment_key');
        $beatMap = (array) ($architecture['beat_map'] ?? []);
        $branchingIndexes = collect($beatMap)
            ->filter(fn ($beat): bool => is_array($beat)
                && str_starts_with(
                    mb_strtoupper(mb_trim((string) ($beat['choice_slot'] ?? ''))),
                    'BRANCHING',
                ))
            ->keys();
        $alreadyBoundMoments = $branchingIndexes
            ->map(fn ($index) => is_array($beatMap[$index] ?? null)
                ? ($beatMap[$index]['moment_key'] ?? null)
                : null)
            ->filter()
            ->values();
        $reservedIndexes = collect();

        foreach ($rolesByMoment as $momentKey => $roles) {
            if ($alreadyBoundMoments->contains($momentKey)) {
                continue;
            }

            $roleText = $roles
                ->map(fn ($role): string => is_array($role) ? (string) ($role['why'] ?? '') : '')
                ->implode(' ');
            $candidateIndex = $branchingIndexes
                ->reject(fn ($index): bool => $reservedIndexes->contains($index))
                ->filter(function ($index) use ($beatMap, $rolesByMoment): bool {
                    $boundMoment = is_array($beatMap[$index] ?? null)
                        ? ($beatMap[$index]['moment_key'] ?? null)
                        : null;

                    return ! $rolesByMoment->has($boundMoment);
                })
                ->sortByDesc(fn ($index): int => $this->semanticScore(
                    $roleText,
                    is_array($beatMap[$index] ?? null)
                        ? (string) (($beatMap[$index]['dramatic_function'] ?? '').' '.($beatMap[$index]['moment'] ?? ''))
                        : '',
                ))
                ->first();

            if ($candidateIndex === null || ! is_array($beatMap[$candidateIndex] ?? null)) {
                continue;
            }

            $draftMoment = $draftMoments->get($momentKey);
            $beatMap[$candidateIndex]['moment_key'] = $momentKey;
            if (is_array($draftMoment)) {
                $beatMap[$candidateIndex]['moment'] = (string) ($draftMoment['runtime_goal'] ?? $momentKey);
            }
            $beatMap[$candidateIndex]['dramatic_function'] = mb_trim(
                (string) ($beatMap[$candidateIndex]['dramatic_function'] ?? '')
                ." Draft choice authority: {$roleText}"
            );
            $reservedIndexes->push($candidateIndex);
            $alreadyBoundMoments->push($momentKey);
        }

        $architecture['beat_map'] = $beatMap;

        return $architecture;
    }

    private function semanticScore(string $authority, string $candidate): int
    {
        $authorityTokens = $this->tokens($authority);
        $candidateTokens = $this->tokens($candidate);
        $score = count(array_intersect($authorityTokens, $candidateTokens)) * 10;

        foreach ([
            ['stealth', 'confrontation', 'method', 'methodology', 'approach', 'tactic'],
            ['identity', 'self', 'trust', 'loyalty'],
            ['moral', 'mercy', 'justice', 'truth', 'protection'],
            ['commitment', 'future', 'promise', 'session', 'end'],
        ] as $signals) {
            if (
                array_intersect($signals, $authorityTokens) !== []
                && array_intersect($signals, $candidateTokens) !== []
            ) {
                $score += 25;
            }
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $text): array
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($text)) ?? '';

        return collect(preg_split('/\s+/u', mb_trim($normalized)) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 4)
            ->unique()
            ->values()
            ->all();
    }
}
