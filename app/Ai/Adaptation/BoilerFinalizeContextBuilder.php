<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\Story;
use RuntimeException;

final class BoilerFinalizeContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Story $story, int $sessionNumber): array
    {
        $adaptation = $story->adaptation;
        $session = $adaptation?->sessionAdaptations()
            ->where('session_number', $sessionNumber)
            ->firstOrFail();

        if (! $adaptation || ! $session || empty($session->boiler_draft)) {
            throw new RuntimeException('Boiler Finalize requires a persisted Boiler Draft and session adaptation.');
        }

        $storyMap = (array) ($adaptation->story_session_map ?? []);

        return [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
            ],
            'session_number' => $sessionNumber,
            'protagonist' => PlayableProtagonist::for($story),
            'cold_open' => (string) data_get($session->entry_point_diagnosis, 'cold_open', ''),
            'boiling_guidance' => collect((array) data_get($storyMap, 'boiling_profile.per_session_guidance', []))
                ->firstWhere('session_number', $sessionNumber),
            'boiler_draft' => (array) $session->boiler_draft,
            'session_architecture' => (array) $session->session_architecture,
            'choice_design' => (array) $session->session_choice_design,
            'consequence_mapping' => (array) $session->choice_consequence_map,
            'session_close' => (array) $session->session_close_design,
            'story_guard_canon' => (array) ($storyMap['story_guard_canon'] ?? []),
            'voice_constraints' => VoiceProfilePromptSlice::dnaAndBans((array) ($adaptation->voice_profile ?? [])),
        ];
    }
}
