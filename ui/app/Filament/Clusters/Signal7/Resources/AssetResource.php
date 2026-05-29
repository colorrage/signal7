<?php

namespace App\Filament\Clusters\Signal7\Resources;

use App\Filament\Clusters\Signal7\Resources\AssetResource\Pages;
use App\Filament\Clusters\Signal7\Signal7Cluster;
use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    protected static ?string $cluster = Signal7Cluster::class;

    protected static ?string $model = Asset::class;

    protected static ?string $navigationLabel = 'Assets';

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
            'index' => Pages\ListAssets::route('/'),
        ];
    }
}
