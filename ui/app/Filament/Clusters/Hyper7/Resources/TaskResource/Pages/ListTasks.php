<?php

namespace App\Filament\Clusters\Hyper7\Resources\TaskResource\Pages;

use App\Filament\Clusters\Hyper7\Resources\TaskResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    public function getTabs(): array
    {
        return [
            'active' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->active()),
            'archive' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->archived()),
        ];
    }
}
