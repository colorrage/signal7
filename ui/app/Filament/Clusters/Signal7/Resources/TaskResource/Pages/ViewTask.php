<?php

namespace App\Filament\Clusters\Signal7\Resources\TaskResource\Pages;

use App\Filament\Clusters\Signal7\Resources\TaskResource;
use App\Livewire\GatePanel;
use App\Models\Task;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use League\CommonMark\CommonMarkConverter;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected string $view = 'filament.pages.view-task';

    public string $activeTaskTab = 'overview';

    public function infolist(Schema $infolist): Schema
    {
        return $infolist->components([

            // -----------------------------------------------------------------
            // 1. GatePanel Livewire component — embeds compliance banner and
            //    gate action surface. Renders the compliance banner regardless
            //    of awaiting state; renders the action surface only when non-null.
            // -----------------------------------------------------------------
            Livewire::make(GatePanel::class, fn (Task $record) => ['task' => $record]),

            // -----------------------------------------------------------------
            // 3. Default attribute display
            // -----------------------------------------------------------------
            Section::make('Overview')->schema([
                TextEntry::make('task_id')->label('ID'),
                TextEntry::make('title'),
                TextEntry::make('phase')->badge(),
                TextEntry::make('scope')->badge(),
                TextEntry::make('awaiting')->placeholder('none'),
                TextEntry::make('compliance_status')->label('Compliance')->placeholder('—'),
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

            // -----------------------------------------------------------------
            // 4. Assets RepeatableEntry with gate chips
            // -----------------------------------------------------------------
            Section::make('Assets')->schema([
                RepeatableEntry::make('assets')
                    ->label('')
                    ->schema([
                        TextEntry::make('asset_id')->label('ID')->badge(),
                        TextEntry::make('title'),
                        TextEntry::make('status')->badge()
                            ->color(fn (?string $state) => match ($state) {
                                'done' => 'success',
                                'in-progress' => 'warning',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('external_gate')
                            ->label('Ext. Gate')
                            ->formatStateUsing(fn ($state) => is_array($state) ? ($state['status'] ?? 'set') : null)
                            ->badge()
                            ->color(fn ($state) => match (is_array($state) ? ($state['status'] ?? null) : null) {
                                'satisfied' => 'success',
                                'pending' => 'warning',
                                'blocked' => 'danger',
                                'waived' => 'gray',
                                default => 'gray',
                            })
                            ->tooltip(fn ($state) => is_array($state)
                                ? implode(' | ', array_filter([
                                    $state['description'] ?? null,
                                    isset($state['source_system']) ? 'via '.$state['source_system'] : null,
                                    isset($state['required_status']) ? 'requires: '.$state['required_status'] : null,
                                    isset($state['evidence']) ? 'evidence: '.$state['evidence'] : null,
                                    isset($state['checked_at']) ? 'checked: '.$state['checked_at'] : null,
                                ]))
                                : null
                            )
                            ->hidden(fn ($state) => empty($state)),
                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->dateTime()
                            ->badge()
                            ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success')
                            ->tooltip(fn ($state) => $state?->toDateTimeString())
                            ->hidden(fn ($state) => $state === null),
                    ])
                    ->columns(5),
            ]),

        ]);
    }
}
