<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Machina\MachinaServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            MachinaServiceProvider::class,
        ];
    }
}
