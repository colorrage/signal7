<?php

namespace App\Filament\Clusters\Signal7\Resources\TaskResource\RelationManagers;

use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset_id')
                    ->label('ID')
                    ->badge()
                    ->sortable()
                    ->extraCellAttributes(fn (Asset $record): array => [
                        'data-asset-id' => $record->asset_id,
                    ]),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('asset_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('channel')
                    ->badge(),
                TextColumn::make('language'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('depends')
                    ->label('Depends')
                    ->formatStateUsing(
                        fn (?array $state): string => $state ? implode(', ', $state) : '—'
                    ),
                TextColumn::make('publish_at')
                    ->label('Publish At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('external_gate')
                    ->label('Gate')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('asset_type')
                    ->label('Type')
                    ->options(fn ($livewire): array => Asset::where('task_id', $livewire->ownerRecord->id)
                        ->distinct()
                        ->orderBy('asset_type')
                        ->pluck('asset_type', 'asset_type')
                        ->filter()
                        ->toArray()),
                SelectFilter::make('channel')
                    ->label('Channel')
                    ->options(fn ($livewire): array => Asset::where('task_id', $livewire->ownerRecord->id)
                        ->distinct()
                        ->orderBy('channel')
                        ->pluck('channel', 'channel')
                        ->filter()
                        ->toArray()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn ($livewire): array => Asset::where('task_id', $livewire->ownerRecord->id)
                        ->distinct()
                        ->orderBy('status')
                        ->pluck('status', 'status')
                        ->filter()
                        ->toArray()),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Asset $record): string => "Asset {$record->asset_id} — {$record->title}")
                    ->modalContent(fn (Asset $record): View => view(
                        'filament.clusters.signal7.asset-detail-modal',
                        ['record' => $record]
                    ))
                    ->modalSubmitAction(false),
                Action::make('revisionHistory')
                    ->label('Revision History')
                    ->icon('heroicon-o-clock')
                    ->modalHeading(fn (Asset $record): string => "Revision History — {$record->asset_id}")
                    ->modalWidth('7xl')
                    ->modalContent(fn (Asset $record): View => view(
                        'filament.clusters.signal7.asset-revision-history-modal',
                        ['record' => $record]
                    ))
                    ->modalSubmitAction(false),
            ])
            ->emptyStateHeading('No assets yet')
            ->emptyStateDescription('This campaign has no planned assets.');
    }
}
