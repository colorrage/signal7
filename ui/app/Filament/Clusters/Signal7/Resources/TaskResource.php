<?php

namespace App\Filament\Clusters\Signal7\Resources;

use App\Filament\Clusters\Signal7\Resources\TaskResource\Pages;
use App\Filament\Clusters\Signal7\Resources\TaskResource\RelationManagers\AssetsRelationManager;
use App\Filament\Clusters\Signal7\Signal7Cluster;
use App\Models\Task;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use League\CommonMark\CommonMarkConverter;

class TaskResource extends Resource
{
    protected static ?string $cluster = Signal7Cluster::class;

    protected static ?string $model = Task::class;

    protected static ?string $navigationLabel = 'Tasks';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->bySystem('signal7');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Overview')->schema([
                TextEntry::make('task_id')->label('ID'),
                TextEntry::make('title'),
                TextEntry::make('phase')->badge(),
                TextEntry::make('scope')->badge(),
                TextEntry::make('awaiting')->placeholder('none'),
                TextEntry::make('created_at_disk')->label('Created')->dateTime(),
            ])->columns(2),
            Section::make('Dashboard Summary')->schema([
                TextEntry::make('dashboard_summary')
                    ->label('')
                    ->html()
                    ->formatStateUsing(
                        fn (?string $state): string => $state
                            ? (new CommonMarkConverter)->convert($state)->getContent()
                            : '<em>No summary available.</em>'
                    )
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('task_id')->label('ID')->badge()->sortable(),
                TextColumn::make('title')->searchable()->limit(60),
                TextColumn::make('phase')->badge()->sortable(),
                TextColumn::make('scope')->badge()->sortable(),
                TextColumn::make('awaiting')->badge()->color('warning')->placeholder('—'),
                TextColumn::make('created_at_disk')->label('Created')->date()->sortable(),
                TextColumn::make('dashboard_summary')->label('Summary')->limit(80)->wrap(),
            ])
            ->filters([
                SelectFilter::make('phase')->options(
                    fn () => Task::bySystem('signal7')->distinct()->pluck('phase', 'phase')->toArray()
                ),
                SelectFilter::make('scope')->options(
                    fn () => Task::bySystem('signal7')->distinct()->pluck('scope', 'scope')->toArray()
                ),
            ])
            ->poll('10s');
    }

    public static function getRelations(): array
    {
        return [
            AssetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'view' => Pages\ViewTask::route('/{record}'),
        ];
    }
}
