<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

final class ChoiceDesignCanonicalizer
{
    /**
     * Bind the semantically matching canonical branching choice to each Draft
     * CHOICE-role moment without changing IDs, categories, paths, or prose.
     *
     * @param  array<string, mixed>  $choiceDesign
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function canonicalize(array $choiceDesign, array $draft): array
    {
        $rolesByMoment = collect((array) ($draft['event_role_map'] ?? []))
            ->filter(fn ($role): bool => is_array($role)
                && ($role['role'] ?? null) === 'CHOICE'
                && is_string($role['included_moment_key'] ?? null)
                && $role['included_moment_key'] !== '')
            ->groupBy('included_moment_key');
        $choices = (array) ($choiceDesign['branching_choices'] ?? []);
        $alreadyBoundMoments = collect($choices)
            ->pluck('moment_key')
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
            $candidateIndex = collect($choices)
                ->keys()
                ->reject(fn ($index): bool => $reservedIndexes->contains($index))
                ->filter(function ($index) use ($choices, $rolesByMoment): bool {
                    $boundMoment = is_array($choices[$index] ?? null)
                        ? ($choices[$index]['moment_key'] ?? null)
                        : null;

                    return ! $rolesByMoment->has($boundMoment);
                })
                ->sortByDesc(fn ($index): int => $this->semanticScore(
                    $roleText,
                    is_array($choices[$index] ?? null)
                        ? implode(' ', [
                            (string) ($choices[$index]['category'] ?? ''),
                            (string) ($choices[$index]['what_this_choice_tracks'] ?? ''),
                            (string) ($choices[$index]['source_moment'] ?? ''),
                            (string) ($choices[$index]['choice_question'] ?? ''),
                        ])
                        : '',
                ))
                ->first();

            if ($candidateIndex === null || ! is_array($choices[$candidateIndex] ?? null)) {
                continue;
            }

            $choices[$candidateIndex]['moment_key'] = $momentKey;
            $reservedIndexes->push($candidateIndex);
            $alreadyBoundMoments->push($momentKey);
        }

        $choiceDesign['branching_choices'] = $choices;

        return $choiceDesign;
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
