<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\Feedback\Schemas;

use App\Models\Feedback;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class FeedbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->heading('Submission')
                    ->schema([
                        TextEntry::make('content')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        ImageEntry::make('screenshot_path')
                            ->label('Page screenshot')
                            ->getStateUsing(fn (Feedback $record): ?string => $record->screenshotUrl())
                            ->checkFileExistence(false)
                            ->imageHeight('70vh')
                            ->columnSpanFull()
                            ->extraImgAttributes([
                                'class' => 'max-w-full rounded-lg border border-gray-200 object-contain object-top dark:border-gray-700',
                            ])
                            ->visible(fn (Feedback $record): bool => filled($record->screenshot_path)),
                    ]),
                Section::make()
                    ->columnSpanFull()
                    ->heading('Context')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('User email')
                            ->placeholder('Guest (not signed in)'),
                        TextEntry::make('page_url')
                            ->label('Page URL')
                            ->placeholder('-')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('user_agent')
                            ->label('User agent')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make()
                    ->columnSpanFull()
                    ->heading('Timestamps')
                    ->columns(2)
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
}
