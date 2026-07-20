<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\SessionAdaptation;

final class BoilerFinalCutCanonicalizer
{
    /**
     * Overlay references owned by validated upstream artifacts.
     *
     * Finalize may author titles, opening images, budgets, and bridges, but it
     * must not reinterpret identity, routing, choice bindings, or event roles.
     *
     * @param  array<string, mixed>  $finalCut
     * @return array<string, mixed>
     */
    public function canonicalize(array $finalCut, SessionAdaptation $session): array
    {
        $draft = (array) $session->boiler_draft;
        $draftMoments = collect((array) ($draft['moment_drafts'] ?? []))
            ->sortBy('moment_order')
            ->values();
        $choiceDesign = (array) $session->session_choice_design;
        $choiceIdsByMoment = collect([
            ...(array) ($choiceDesign['branching_choices'] ?? []),
            ...(array) ($choiceDesign['emotional_choices'] ?? []),
        ])
            ->filter(fn ($choice): bool => is_array($choice)
                && is_string($choice['choice_id'] ?? null)
                && is_string($choice['moment_key'] ?? null))
            ->groupBy('moment_key')
            ->map(fn ($choices): array => $choices->pluck('choice_id')->values()->all());

        $finalCut['boiler_version'] = $draft['boiler_version'] ?? '1.0';
        $finalCut['session_number'] = $session->session_number;
        $finalCut['cut_key'] = 'standard';
        $finalCut['playable_promise'] = $draft['playable_promise'] ?? '';

        $cards = (array) ($finalCut['moments'] ?? []);
        $lastCardIndex = count($cards) - 1;
        foreach ($cards as $index => $card) {
            if (! is_array($card)) {
                continue;
            }

            $draftMoment = $draftMoments->get($index);
            if (! is_array($draftMoment)) {
                continue;
            }

            $momentKey = (string) ($draftMoment['moment_key'] ?? '');
            $nextDraftMoment = $draftMoments->get($index + 1);

            $card['moment_key'] = $momentKey;
            $card['moment_order'] = $index + 1;
            $card['source_event_ids'] = (array) ($draftMoment['source_event_ids'] ?? []);
            $card['anchor_event_id'] = $draftMoment['anchor_event_id'] ?? null;
            $card['choice_ids'] = $choiceIdsByMoment->get($momentKey, []);
            $card['next_moment_key'] = is_array($nextDraftMoment)
                ? (string) ($nextDraftMoment['moment_key'] ?? '')
                : '';
            if (
                $index === $lastCardIndex
                && (! is_string($card['next_bridge'] ?? null) || mb_trim($card['next_bridge']) === '')
            ) {
                $card['next_bridge'] = $this->finalBridgeFallback($session);
            }

            $cards[$index] = $card;
        }
        $finalCut['moments'] = $cards;

        // Only moments that Phase 5 assigned a canonical branching choice carry the
        // CHOICE role in the final cut. Draft CHOICE candidates that were not
        // selected by Phase 5 are reclassified as PRESSURE — they exert narrative
        // weight without owning a canonical branching ID.
        $canonicalChoiceMoments = $choiceIdsByMoment->keys()->all();

        $finalCut['event_role_map'] = collect((array) ($draft['event_role_map'] ?? []))
            ->map(function ($role) use ($canonicalChoiceMoments): array {
                $draftRole = $role['role'] ?? null;
                $momentKey = $role['included_moment_key'] ?? '';
                $effectiveRole = ($draftRole === 'CHOICE' && ! in_array($momentKey, $canonicalChoiceMoments, true))
                    ? 'PRESSURE'
                    : $draftRole;

                return [
                    'event_id' => $role['event_id'] ?? null,
                    'primary_role' => $effectiveRole,
                    'included_moment_key' => $momentKey,
                    'why' => $role['why'] ?? null,
                    'reintroduction_note' => $role['reintroduction_note'] ?? '',
                ];
            })
            ->values()
            ->all();

        return $finalCut;
    }

    private function finalBridgeFallback(SessionAdaptation $session): string
    {
        $close = (array) $session->session_close_design;

        foreach (['hook_transition', 'next_bridge_guidance'] as $field) {
            $value = $close[$field] ?? null;
            if (is_string($value) && mb_trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }
}
