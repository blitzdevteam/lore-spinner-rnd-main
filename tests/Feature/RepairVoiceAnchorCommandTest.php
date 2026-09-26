<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function corruptedVoiceAnchorStory(): Story
{
    $story = Story::factory()->create();

    StoryAdaptation::create([
        'story_id' => $story->id,
        'adaptation_status' => AdaptationStatusEnum::COMPLETED,
        'voice_profile' => [
            'anchor_card' => ['Delete every em dash.'],
            'voice_anchor' => [
                ['mode' => 'quiet', 'prose' => "You wait.','session_relevance':'later_session"],
                ['mode' => 'action', 'prose' => 'You run.', 'session_relevance' => 'all_sessions'],
            ],
        ],
    ]);

    return $story;
}

it('reports corruption on a dry run without writing', function (): void {
    $story = corruptedVoiceAnchorStory();

    $this->artisan('stories:repair-voice-anchor', ['story' => [$story->slug], '--dry-run' => true])
        ->expectsTable(['Story', 'Corrupted exemplars', 'Result'], [[$story->slug, 1, 'would repair']])
        ->assertSuccessful();

    expect($story->adaptation()->first()->voice_profile['voice_anchor'][0]['prose'])
        ->toContain('session_relevance');
});

it('repairs stored voice anchors once and is a no-op afterwards', function (): void {
    $story = corruptedVoiceAnchorStory();

    $this->artisan('stories:repair-voice-anchor', ['story' => [$story->slug]])->assertSuccessful();

    $profile = $story->adaptation()->first()->voice_profile;

    expect($profile['voice_anchor'][0])->toBe(['mode' => 'quiet', 'prose' => 'You wait.', 'session_relevance' => 'later_session'])
        ->and($profile['voice_anchor'][1]['prose'])->toBe('You run.')
        ->and($profile['anchor_card'])->toBe(['Delete every em dash.']);

    $this->artisan('stories:repair-voice-anchor', ['story' => [$story->slug]])
        ->expectsTable(['Story', 'Corrupted exemplars', 'Result'], [[$story->slug, 0, 'clean']])
        ->assertSuccessful();
});
