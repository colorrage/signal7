<?php

namespace App\Providers;

use App\Contracts\CliCommandDispatcher;
use App\Services\QueuedCliCommandDispatcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            CliCommandDispatcher::class,
            QueuedCliCommandDispatcher::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
