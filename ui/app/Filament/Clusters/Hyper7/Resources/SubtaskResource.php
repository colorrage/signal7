<?php

namespace App\Filament\Clusters\Hyper7\Resources;

use App\Filament\Clusters\Hyper7\Hyper7Cluster;
use App\Filament\Clusters\Hyper7\Resources\SubtaskResource\Pages;
use App\Models\Task;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SubtaskResource extends Resource
{
    protected static ?string $cluster = Hyper7Cluster::class;

    // TODO: T6 will create a dedicated subtasks table; update $model when ready
    protected static ?string $model = Task::class;

    protected static ?string $navigationLabel = 'Subtasks';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubtasks::route('/'),
        ];
    }
}
