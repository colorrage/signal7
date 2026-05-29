<?php

namespace App\Filament\Clusters\Signal7\Resources\BacklogResource\Pages;

use App\Filament\Clusters\Signal7\Resources\BacklogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBacklog extends ListRecords
{
    protected static string $resource = BacklogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
