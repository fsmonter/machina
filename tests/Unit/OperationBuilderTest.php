<?php

declare(strict_types=1);

use Machina\Operation;
use Tests\TestState;

it('registers transition in graph when operation has target', function () {
    $machine = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('start')->target(TestState::Processing);
        })
        ->final(TestState::Completed)
        ->build(TestState::class);

    expect($machine->getTransitions(TestState::Pending))->toContain(TestState::Processing);
});

it('creates state-bound operation without target', function () {
    $machine = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('notify')->action(fn () => null);
            $state->on('start')->target(TestState::Processing);
        })
        ->build(TestState::class);

    $op = $machine->findOperation(TestState::Pending, 'notify');

    expect($op)->toBeInstanceOf(Operation::class);
    expect($op->to)->toBeNull();
    expect($op->action)->not->toBeNull();
});

it('attaches guard to operation', function () {
    $guard = fn () => true;

    $machine = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) use ($guard) {
            $state->on('start')->target(TestState::Processing)->guard($guard);
        })
        ->build(TestState::class);

    $op = $machine->findOperation(TestState::Pending, 'start');

    expect($op->guards)->toHaveCount(1);
});

it('supports multiple operations on the same state', function () {
    $machine = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('start')->target(TestState::Processing);
            $state->on('cancel')->target(TestState::Cancelled);
        })
        ->build(TestState::class);

    expect($machine->getOperations(TestState::Pending))->toHaveCount(2);
    expect($machine->findOperation(TestState::Pending, 'start'))->not->toBeNull();
    expect($machine->findOperation(TestState::Pending, 'cancel'))->not->toBeNull();
});

it('rejects duplicate operation names on the same state', function () {
    machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('start')->target(TestState::Processing);
            $state->on('start')->target(TestState::Cancelled);
        });
})->throws(InvalidArgumentException::class, "Duplicate operation 'start' for state pending");

it('allows same operation name on different states', function () {
    $machine = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('cancel')->target(TestState::Cancelled);
        })
        ->state(TestState::Processing, function (Machina\StateBuilder $state) {
            $state->on('cancel')->target(TestState::Cancelled);
        })
        ->build(TestState::class);

    expect($machine->findOperation(TestState::Pending, 'cancel'))->not->toBeNull();
    expect($machine->findOperation(TestState::Processing, 'cancel'))->not->toBeNull();
});

it('attaches action to operation', function () {
    $actionFn = fn () => null;

    $machine = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) use ($actionFn) {
            $state->on('start')->target(TestState::Processing)->action($actionFn);
        })
        ->build(TestState::class);

    $op = $machine->findOperation(TestState::Pending, 'start');

    expect($op->action)->toBe($actionFn);
});

it('accepts guard as array of closures', function () {
    $machine = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('start')->target(TestState::Processing)
                ->guard([fn () => true, fn () => false]);
        })
        ->build(TestState::class);

    $op = $machine->findOperation(TestState::Pending, 'start');

    expect($op->guards)->toHaveCount(2);
});
