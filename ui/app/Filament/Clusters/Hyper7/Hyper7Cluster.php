<?php

namespace App\Filament\Clusters\Hyper7;

use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class Hyper7Cluster extends Cluster
{
    protected static ?string $navigationLabel = 'Hyper7';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;
}
