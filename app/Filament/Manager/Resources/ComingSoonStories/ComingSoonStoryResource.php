<?php

declare(strict_types=1);

namespace App\Filament\Manager\Resources\ComingSoonStories;

use App\Filament\Manager\Resources\ComingSoonStories\Pages\CreateComingSoonStory;
use App\Filament\Manager\Resources\ComingSoonStories\Pages\EditComingSoonStory;
use App\Filament\Manager\Resources\ComingSoonStories\Pages\ListComingSoonStories;
use App\Filament\Manager\Resources\ComingSoonStories\Schemas\ComingSoonStoryForm;
use App\Filament\Manager\Resources\ComingSoonStories\Tables\ComingSoonStoriesTable;
use App\Models\ComingSoonStory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class ComingSoonStoryResource extends Resource
{
    protected static ?string $model = ComingSoonStory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    public static function getNavigationBadge(): string
    {
        return (string) self::getEloquentQuery()->count();
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ComingSoonStoryForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ComingSoonStoriesTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListComingSoonStories::route('/'),
            'create' => CreateComingSoonStory::route('/create'),
            'edit' => EditComingSoonStory::route('/{record}/edit'),
        ];
    }
}
