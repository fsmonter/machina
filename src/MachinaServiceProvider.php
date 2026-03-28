<?php

declare(strict_types=1);

namespace Machina;

use Illuminate\Support\ServiceProvider;
use Machina\Console\MakeMachinaCommand;

class MachinaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeMachinaCommand::class,
            ]);
        }
    }
}
