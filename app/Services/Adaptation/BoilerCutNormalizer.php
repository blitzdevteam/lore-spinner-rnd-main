<?php

declare(strict_types=1);

namespace App\Services\Adaptation;

use App\Enums\BoilerStatus;
use App\Models\PlayableSessionCut;
use App\Models\SessionAdaptation;
use Illuminate\Support\Facades\DB;

final class BoilerCutNormalizer
{
    /**
     * @param  array<string, mixed>  $finalCut
     */
    public function normalize(SessionAdaptation $session, array $finalCut): PlayableSessionCut
    {
        return DB::transaction(function () use ($session, $finalCut): PlayableSessionCut {
            $lockedSession = SessionAdaptation::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();
            $storyId = $lockedSession->storyAdaptation()->value('story_id');

            $lockedSession->playableCut()->delete();

            $cut = $lockedSession->playableCut()->create([
                'story_id' => $storyId,
                'session_number' => $lockedSession->session_number,
                'cut_key' => 'standard',
                'status' => 'finalized',
            ]);

            foreach ((array) ($finalCut['moments'] ?? []) as $card) {
                if (($card['next_moment_key'] ?? '') === '') {
                    unset($card['next_moment_key']);
                }

                $cut->moments()->create([
                    'moment_key' => $card['moment_key'],
                    'moment_order' => $card['moment_order'],
                    'anchor_event_id' => $card['anchor_event_id'],
                    'source_event_ids' => $card['source_event_ids'],
                    'scene_card' => $card,
                ]);
            }

            foreach ((array) ($finalCut['event_role_map'] ?? []) as $role) {
                $cut->eventRoles()->create([
                    'event_id' => $role['event_id'],
                    'primary_role' => $role['primary_role'],
                    'included_moment_key' => ($role['included_moment_key'] ?? '') !== ''
                        ? $role['included_moment_key']
                        : null,
                    'why' => $role['why'],
                    'reintroduction_note' => ($role['reintroduction_note'] ?? '') !== ''
                        ? $role['reintroduction_note']
                        : null,
                ]);
            }

            $moments = (array) ($finalCut['moments'] ?? []);
            $manifest = [
                'boiler_version' => '1.0',
                'session_number' => $lockedSession->session_number,
                'playable_promise' => $finalCut['playable_promise'],
                'moment_manifest' => array_map(
                    static fn (array $card): array => [
                        'moment_key' => $card['moment_key'],
                        'moment_order' => $card['moment_order'],
                    ],
                    $moments,
                ),
                'coverage_summary' => [
                    'source_events' => count((array) ($finalCut['event_role_map'] ?? [])),
                    'events_accounted_for' => count((array) ($finalCut['event_role_map'] ?? [])),
                ],
                'pacing_summary' => [
                    'moment_count' => count($moments),
                    'first_agency_moment' => $moments[0]['moment_key'] ?? null,
                ],
            ];

            $lockedSession->update([
                'boiler_final_cut' => $manifest,
                'boiler_status' => BoilerStatus::FINALIZED,
                'boiler_error' => null,
                'boiler_finalized_at' => now(),
            ]);

            return $cut->load(['moments', 'eventRoles']);
        });
    }
}
