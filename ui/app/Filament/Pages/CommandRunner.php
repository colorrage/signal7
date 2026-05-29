<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CommandRunner extends Page
{
    protected static ?string $navigationLabel = 'Command Runner';

    protected static string|\UnitEnum|null $navigationGroup = 'DevTools';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected string $view = 'filament.pages.command-runner';
}
