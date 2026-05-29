<?php

namespace App\Filament\Clusters\Signal7\Resources;

use App\Filament\Clusters\Signal7\Resources\BacklogResource\Pages;
use App\Filament\Clusters\Signal7\Signal7Cluster;
use App\Filament\Pages\CommandRunner;
use App\Models\BacklogEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BacklogResource extends Resource
{
    protected static ?string $cluster = Signal7Cluster::class;

    protected static ?string $model = BacklogEntry::class;

    protected static ?string $navigationLabel = 'Backlog';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->bySystem('signal7');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_id')->label('ID')->sortable(),
                TextColumn::make('title')->searchable()->limit(80),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'promoted' => 'warning',
                        'dropped' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->actions([
                Action::make('promote')
                    ->label('Promote')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->url(fn (BacklogEntry $record): string => CommandRunner::getUrl([
                        'command' => 'signal-backlog.promote',
                        'arg_entry_id' => $record->entry_id,
                    ]))
                    ->hidden(fn (BacklogEntry $record) => $record->status !== 'open'),
                Action::make('drop')
                    ->label('Drop')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (BacklogEntry $record) => $record->update(['status' => 'dropped']))
                    ->hidden(fn (BacklogEntry $record) => $record->status !== 'open'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBacklog::route('/'),
        ];
    }
}
