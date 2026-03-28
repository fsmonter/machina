<?php

declare(strict_types=1);

namespace Machina\Console;

use Illuminate\Console\GeneratorCommand;

class MakeMachinaCommand extends GeneratorCommand
{
    protected $name = 'make:machina';

    protected $description = 'Create a new state machine definition';

    protected $type = 'Machina';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/machina.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Machina';
    }
}
