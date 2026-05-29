<?php

namespace Tests\Feature;

use App\Filament\Pages\CommandRunner;
use App\Livewire\CommandRunner as CommandRunnerComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContextualCommandRunnerLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_signal_backlog_promote_url_prefills_runner(): void
    {
        $url = CommandRunner::getUrl([
            'command' => 'signal-backlog.promote',
            'arg_entry_id' => 'B3',
        ]);

        $this->assertStringContainsString('command=signal-backlog.promote', urldecode($url));
        $this->assertStringContainsString('arg_entry_id=B3', urldecode($url));

        Livewire::withQueryParams([
            'command' => 'signal-backlog.promote',
            'arg_entry_id' => 'B3',
        ])
            ->test(CommandRunnerComponent::class)
            ->assertSet('selectedCommand', 'signal-backlog.promote')
            ->assertSet('arguments.entry_id', 'B3')
            ->assertSet('confirming', true);
    }

    public function test_recipe_run_url_prefills_runner(): void
    {
        $url = CommandRunner::getUrl([
            'command' => 'signal-recipe.run',
            'arg_recipe' => 'weekly-social',
        ]);

        $this->assertStringContainsString('command=signal-recipe.run', urldecode($url));
        $this->assertStringContainsString('arg_recipe=weekly-social', urldecode($url));

        Livewire::withQueryParams([
            'command' => 'signal-recipe.run',
            'arg_recipe' => 'weekly-social',
        ])
            ->test(CommandRunnerComponent::class)
            ->assertSet('selectedCommand', 'signal-recipe.run')
            ->assertSet('arguments.recipe', 'weekly-social')
            ->assertSet('confirming', true);
    }
}
