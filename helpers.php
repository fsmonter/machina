<?php

declare(strict_types=1);

use Machina\StateMachineBuilder;

if (! function_exists('machina')) {
    function machina(): StateMachineBuilder
    {
        return new StateMachineBuilder;
    }
}
