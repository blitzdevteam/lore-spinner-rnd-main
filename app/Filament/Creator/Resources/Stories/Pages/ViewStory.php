<?php

declare(strict_types=1);

namespace App\Filament\Creator\Resources\Stories\Pages;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Filament\Creator\Resources\Stories\StoryResource;
use App\Jobs\Adaptation\RunAdaptationPipelineJob;
use App\Models\Story;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ViewStory extends ViewRecord
{
    protected static string $resource = StoryResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark-as-published')
                ->label('Mark as Published')
                ->modalWidth('3xl')
                ->modalDescription('This action will mark the story as published and make it visible to the public. This action cannot be undone.')
                ->action(function (array $data, Story $story): void {
                    $story->update([
                        'opening' => $data['opening'],
                        'status' => StoryStatusEnum::PUBLISHED,
                    ]);
                })
                ->color(Color::Green)
                ->icon(Heroicon::ExclamationTriangle)
                ->requiresConfirmation()
                ->schema([
                    RichEditor::make('opening')
                        ->required()
                        ->helperText('This will be used as the opening of the story when it is published')
                        ->toolbarButtons([
                            'redo', 'undo', 'underline', 'italic', 'bold',
                        ])
                        ->columnSpan(2),
                ])
                ->visible(fn (Story $story): bool => $story->canMarkAsPublished()),

            Action::make('run-adaptation')
                ->label('Run Adaptation')
                ->schema([
                    Toggle::make('boiler_enabled')
                        ->label('Enable Boiler pacing')
                        ->helperText('Compile this adaptation into paced playable moments.')
                        ->default(false),
                ])
                ->action(function (array $data, Story $story): void {
                    RunAdaptationPipelineJob::dispatch(
                        $story,
                        boilerEnabled: (bool) ($data['boiler_enabled'] ?? false),
                    );
                })
                ->color(Color::Blue)
                ->icon(Heroicon::Sparkles)
                ->requiresConfirmation()
                ->modalDescription('This will run the full adaptation pipeline for this story. The process runs in the background.')
                ->visible(fn (Story $story): bool => $story->chapters()->exists()
                    && (! $story->adaptation || $story->adaptation->adaptation_status === AdaptationStatusEnum::FAILED)),

            Action::make('rerun-adaptation')
                ->label('Re-run Adaptation')
                ->schema([
                    Toggle::make('boiler_enabled')
                        ->label('Enable Boiler pacing')
                        ->helperText('Changing this mode rebuilds the adaptation and resets existing games only after a successful run.'),
                ])
                ->fillForm(fn (Story $story): array => [
                    'boiler_enabled' => (bool) $story->adaptation?->boiler_enabled,
                ])
                ->action(function (array $data, Story $story): void {
                    RunAdaptationPipelineJob::dispatch(
                        $story,
                        force: true,
                        boilerEnabled: (bool) ($data['boiler_enabled'] ?? false),
                    );
                })
                ->color(Color::Orange)
                ->icon(Heroicon::ArrowPath)
                ->requiresConfirmation()
                ->modalDescription('This will reset and re-run the full adaptation pipeline. All existing adaptation data will be replaced.')
                ->visible(fn (Story $story): bool => $story->adaptation
                    && in_array($story->adaptation->adaptation_status, [
                        AdaptationStatusEnum::COMPLETED,
                        AdaptationStatusEnum::PARTIAL_COMPLETION,
                    ])),

            Action::make('export-adaptation-json')
                ->label('Export JSON')
                ->action(function (Story $story): StreamedResponse {
                    $adaptation = $story->adaptation->load('sessionAdaptations');
                    $slug = $story->slug ?? 'story-' . $story->id;

                    $data = [
                        'story' => ['id' => $story->id, 'title' => $story->title, 'slug' => $slug],
                        'exported_at' => now()->toIso8601String(),
                        'adaptation_status' => $adaptation->adaptation_status->value,
                        'story_wide' => [
                            'format_detection' => $adaptation->format_detection,
                            'ip_audit' => $adaptation->ip_audit,
                            'story_session_map' => $adaptation->story_session_map,
                        ],
                        'sessions' => $adaptation->sessionAdaptations->sortBy('session_number')->map(fn ($s) => [
                            'session_number' => $s->session_number,
                            'session_status' => $s->session_status->value,
                            'entry_point_diagnosis' => $s->entry_point_diagnosis,
                            'session_architecture' => $s->session_architecture,
                            'session_choice_design' => $s->session_choice_design,
                            'choice_consequence_map' => $s->choice_consequence_map,
                            'session_close_design' => $s->session_close_design,
                            'editorial_verification' => $s->editorial_verification,
                        ])->values()->toArray(),
                    ];

                    $filename = "adaptation-{$slug}-" . now()->format('Y-m-d_His') . '.json';

                    return response()->streamDownload(function () use ($data) {
                        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }, $filename, ['Content-Type' => 'application/json']);
                })
                ->color(Color::Gray)
                ->icon(Heroicon::ArrowDownTray)
                ->visible(fn (Story $story): bool => $story->adaptation !== null),

            Action::make('export-adaptation-csv')
                ->label('Export CSV')
                ->action(function (Story $story): StreamedResponse {
                    $adaptation = $story->adaptation->load('sessionAdaptations');
                    $slug = $story->slug ?? 'story-' . $story->id;
                    $filename = "adaptation-{$slug}-" . now()->format('Y-m-d_His') . '.csv';

                    return response()->streamDownload(function () use ($adaptation) {
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, ['story_status', 'format_detection', 'ip_audit', 'story_session_map']);
                        fputcsv($handle, [
                            $adaptation->adaptation_status->value,
                            $adaptation->format_detection ? 'done' : 'pending',
                            $adaptation->ip_audit ? 'done' : 'pending',
                            $adaptation->story_session_map ? 'done' : 'pending',
                        ]);
                        fputcsv($handle, []);
                        fputcsv($handle, [
                            'session_number', 'session_status', 'entry_point', 'architecture',
                            'choice_design', 'consequence_map', 'session_close', 'editorial', 'updated_at',
                        ]);

                        foreach ($adaptation->sessionAdaptations->sortBy('session_number') as $s) {
                            fputcsv($handle, [
                                $s->session_number,
                                $s->session_status->value,
                                $s->entry_point_diagnosis ? 'done' : 'pending',
                                $s->session_architecture ? 'done' : 'pending',
                                $s->session_choice_design ? 'done' : 'pending',
                                $s->choice_consequence_map ? 'done' : 'pending',
                                $s->session_close_design ? 'done' : 'pending',
                                $s->editorial_verification ? 'done' : 'pending',
                                $s->updated_at?->toDateTimeString(),
                            ]);
                        }

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv']);
                })
                ->color(Color::Gray)
                ->icon(Heroicon::TableCells)
                ->visible(fn (Story $story): bool => $story->adaptation !== null),

            EditAction::make(),
        ];
    }
}
