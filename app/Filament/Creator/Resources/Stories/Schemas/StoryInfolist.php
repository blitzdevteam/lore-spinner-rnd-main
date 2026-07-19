<?php

declare(strict_types=1);

namespace App\Filament\Creator\Resources\Stories\Schemas;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Enums\Story\StoryRatingEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class StoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->heading('Story details')
                    ->description('View the basic information.')
                    ->columns(2)
                    ->schema([
                        Fieldset::make('Images')
                            ->schema([
                                SpatieMediaLibraryImageEntry::make('cover')
                                    ->label('Cover Image')
                                    ->collection('cover')
                                    ->placeholder('No cover image')
                                    ->columnSpanFull(),
                                SpatieMediaLibraryImageEntry::make('gallery')
                                    ->label('Gallery')
                                    ->collection('gallery')
                                    ->placeholder('No gallery images')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                        Fieldset::make('Basic information')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('category.title')
                                    ->label('Category')
                                    ->placeholder('-'),
                                TextEntry::make('status')
                                    ->color(fn (StoryStatusEnum $state): string => $state->getSeverity())
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('rating')
                                    ->color(fn (StoryRatingEnum $state): string => $state->getSeverity())
                                    ->badge()
                                    ->placeholder('-'),
                            ])
                            ->columnSpanFull(),
                        TextEntry::make('title')
                            ->placeholder('-')
                            ->columnSpan(2),
                        TextEntry::make('teaser')
                            ->placeholder('-')
                            ->columnSpan(2),
                        TextEntry::make('published_at')
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (Story $record): bool => ! is_null($record->published_at))
                            ->columnSpan(2),
                        TextEntry::make('opening')
                            ->html()
                            ->placeholder('-')
                            ->columnSpan(2),
                    ]),
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->heading('Adaptation')
                    ->description('Interactive adaptation pipeline status')
                    ->schema([
                        TextEntry::make('adaptation.adaptation_status')
                            ->label('Overall Status')
                            ->badge()
                            ->color(fn (?AdaptationStatusEnum $state): string => $state?->getSeverity() ?? 'gray')
                            ->placeholder('Not started'),
                        TextEntry::make('adaptation.boiler_enabled')
                            ->label('Boiler Mode')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ? 'Enabled' : 'Disabled')
                            ->color(fn ($state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('adaptation.sessionAdaptations')
                            ->label('Session Progress')
                            ->formatStateUsing(function ($state, Story $record) {
                                $adaptation = $record->adaptation;
                                if (! $adaptation) {
                                    return 'No adaptation data';
                                }
                                $sessions = $adaptation->sessionAdaptations;
                                if ($sessions->isEmpty()) {
                                    return 'No sessions yet';
                                }
                                $completed = $sessions->where('session_status', SessionAdaptationStatusEnum::COMPLETED)->count();
                                $failed = $sessions->where('session_status', SessionAdaptationStatusEnum::FAILED)->count();
                                $parts = ["{$completed}/{$sessions->count()} completed"];
                                if ($failed > 0) {
                                    $parts[] = "{$failed} failed";
                                }

                                return implode(' · ', $parts);
                            })
                            ->placeholder('No sessions'),
                        TextEntry::make('adaptation.updated_at')
                            ->label('Last Updated')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->visible(fn (Story $record): bool => $record->adaptation !== null),
                Section::make()
                    ->columnSpanFull()
                    ->heading('Story-Wide Phases')
                    ->description('Format Detection → IP Audit → Story Session Map')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('adaptation.format_detection')
                            ->label('Phase 0: Format Detection')
                            ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('adaptation.ip_audit')
                            ->label('Phase 1: IP Audit')
                            ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('adaptation.story_session_map')
                            ->label('Phase 2: Story Session Map')
                            ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Story $record): bool => $record->adaptation !== null)
                    ->collapsed(),
                Section::make()
                    ->columnSpanFull()
                    ->heading('Per-Session Phases')
                    ->description('Detailed phase output for each planned session')
                    ->schema([
                        RepeatableEntry::make('adaptation.sessionAdaptations')
                            ->label('')
                            ->schema([
                                TextEntry::make('session_number')
                                    ->label('Session')
                                    ->formatStateUsing(fn ($state) => "Session {$state}")
                                    ->badge(),
                                TextEntry::make('session_status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (?SessionAdaptationStatusEnum $state): string => $state?->getSeverity() ?? 'gray'),
                                TextEntry::make('boiler_status')
                                    ->label('Boiler Status')
                                    ->badge()
                                    ->formatStateUsing(fn (?BoilerStatus $state): string => $state?->value ?? 'pending')
                                    ->color(fn (?BoilerStatus $state): string => match ($state) {
                                        BoilerStatus::FINALIZED => 'success',
                                        BoilerStatus::FAILED => 'danger',
                                        BoilerStatus::DRAFTING, BoilerStatus::FINALIZING => 'info',
                                        default => 'warning',
                                    }),
                                TextEntry::make('entry_point_diagnosis')
                                    ->label('Phase 3: Entry Point')
                                    ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('boiler_draft')
                                    ->label('Phase 3.5: Boiler Draft')
                                    ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('session_architecture')
                                    ->label('Phase 4: Beat Architecture')
                                    ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('session_choice_design')
                                    ->label('Phase 5: Choice Design')
                                    ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('choice_consequence_map')
                                    ->label('Phase 6: Consequence Map')
                                    ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('session_close_design')
                                    ->label('Phase 7: Session Close')
                                    ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('boiler_final_cut')
                                    ->label('Phase 7.5: Boiler Final Manifest')
                                    ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                                    ->html()
                                    ->columnSpanFull(),
                                TextEntry::make('editorial_verification')
                                    ->label('Phase 8: Editorial Verification')
                                    ->formatStateUsing(fn ($state) => $state ? self::formatJson($state) : 'Pending')
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->visible(fn (Story $record): bool => $record->adaptation?->sessionAdaptations->isNotEmpty() ?? false)
                    ->collapsed(),
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->heading('Metadata')
                    ->description('System information')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }

    private static function formatJson(mixed $data): string
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return '<pre style="max-height:300px;overflow:auto;font-size:12px;background:#1e1e2e;color:#cdd6f4;padding:12px;border-radius:8px;white-space:pre-wrap;word-break:break-word;">'
            . e($json)
            . '</pre>';
    }
}
