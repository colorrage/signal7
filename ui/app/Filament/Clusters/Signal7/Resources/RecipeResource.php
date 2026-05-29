<?php

namespace App\Filament\Clusters\Signal7\Resources;

use App\Filament\Clusters\Signal7\Resources\RecipeResource\Pages;
use App\Filament\Clusters\Signal7\Signal7Cluster;
use App\Filament\Pages\CommandRunner;
use App\Models\Recipe;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use League\CommonMark\CommonMarkConverter;

class RecipeResource extends Resource
{
    protected static ?string $cluster = Signal7Cluster::class;

    protected static ?string $model = Recipe::class;

    protected static ?string $navigationLabel = 'Recipes';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->bySystem('signal7');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('filename')->searchable(),
                TextColumn::make('updated_at')->label('Last Modified')->dateTime()->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Recipe $record) => $record->name)
                    ->modalContent(function (Recipe $record): View {
                        $path = config('disksync.signal').'/recipes/'.$record->filename;
                        $html = file_exists($path)
                            ? (new CommonMarkConverter)->convert(file_get_contents($path))->getContent()
                            : '<p class="text-red-500">Recipe file not found on disk.</p>';

                        return view('filament.recipes.view-modal', ['html' => $html]);
                    })
                    ->modalSubmitAction(false),
                Action::make('run')
                    ->label('Run')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->url(fn (Recipe $record): string => CommandRunner::getUrl([
                        'command' => 'signal-recipe.run',
                        'arg_recipe' => $record->name,
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecipes::route('/'),
        ];
    }
}
