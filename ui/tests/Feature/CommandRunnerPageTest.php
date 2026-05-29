<?php

namespace Tests\Feature;

use App\Filament\Pages\CommandRunner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandRunnerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runner_page_renders(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CommandRunner::class)
            ->assertSuccessful()
            ->assertSee('command-runner');
    }
}
