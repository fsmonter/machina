<?php

declare(strict_types=1);

use Maquina\StateMachineBuilder;

if (! function_exists('machine')) {
    function machine(): StateMachineBuilder
    {
        return new StateMachineBuilder;
    }
}
