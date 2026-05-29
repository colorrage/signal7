<?php

namespace App\Filament\Clusters\Signal7;

use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class Signal7Cluster extends Cluster
{
    protected static ?string $navigationLabel = 'Signal7';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;
}
