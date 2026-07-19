<?php

declare(strict_types=1);

namespace App\Filament\Creator\Resources\Stories\Pages;

use App\Enums\Story\StoryStatusEnum;
use App\Filament\Creator\Resources\Stories\StoryResource;
use App\Jobs\Adaptation\RunAdaptationPipelineJob;
use App\Jobs\Chapter\ChapterExtractorJob;
use App\Jobs\Story\StoryBannerGeneratorJob;
use App\Jobs\Story\StoryCoverGeneratorJob;
use App\Jobs\Story\StoryOpeningGeneratorJob;
use App\Jobs\Story\SystemPromptGeneratorJob;
use App\Models\Story;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateStory extends CreateRecord
{
    protected static string $resource = StoryResource::class;

    protected static bool $canCreateAnother = false;

    private bool $boilerEnabled = false;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->boilerEnabled = (bool) ($data['boiler_enabled'] ?? false);
        unset($data['boiler_enabled']);

        $data['creator_id'] = auth()->id();

        return parent::mutateFormDataBeforeCreate($data);
    }

    #[\Override]
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Story $story */
        $story = parent::handleRecordCreation($data);

        if ($data['use_script_upload']) {
            $story->update([
                'status' => StoryStatusEnum::AWAITING_EXTRACTING_CHAPTERS_REQUEST,
            ]);

            ChapterExtractorJob::dispatch($story);
            SystemPromptGeneratorJob::dispatch($story);
            StoryCoverGeneratorJob::dispatch($story);
            StoryBannerGeneratorJob::dispatch($story);
            StoryOpeningGeneratorJob::dispatch($story)->delay(now()->addMinutes(5));

            RunAdaptationPipelineJob::dispatch(
                $story,
                boilerEnabled: $this->boilerEnabled,
            )->delay(now()->addMinutes(2));
        } else {
            $story->update([
                'status' => StoryStatusEnum::DRAFT,
            ]);

            StoryCoverGeneratorJob::dispatch($story);
            StoryBannerGeneratorJob::dispatch($story);
        }

        return $story;
    }
}
