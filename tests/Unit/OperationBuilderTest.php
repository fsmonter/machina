<?php

declare(strict_types=1);

use Machina\Operation;
use Tests\TestState;

it('requires from() before on()', function () {
    machina()->on('test');
})->throws(InvalidArgumentException::class, 'Must call from() before on()');

it('requires on() before do()', function () {
    machina()->from(TestState::Pending)->do(fn () => null);
})->throws(InvalidArgumentException::class, 'Must call on() before do()');

it('registers transition in graph when operation has to()', function () {
    $machine = machina()
        ->from(TestState::Pending)
            ->on('start')->to(TestState::Processing)
        ->final(TestState::Completed)
        ->build(TestState::class);

    expect($machine->getTransitions(TestState::Pending))->toContain(TestState::Processing);
});

it('creates state-bound operation without to()', function () {
    $machine = machina()
        ->from(TestState::Pending)
            ->on('notify')->do(fn () => null)
            ->on('start')->to(TestState::Processing)
        ->build(TestState::class);

    $op = $machine->findOperation(TestState::Pending, 'notify');

    expect($op)->toBeInstanceOf(Operation::class);
    expect($op->to)->toBeNull();
    expect($op->do)->not->toBeNull();
});

it('attaches guard to operation when after on()', function () {
    $guard = fn () => true;

    $machine = machina()
        ->from(TestState::Pending)
            ->on('start')->to(TestState::Processing)->guard($guard)
        ->build(TestState::class);

    $op = $machine->findOperation(TestState::Pending, 'start');

    expect($op->guards)->toHaveCount(1);
});

it('supports multiple operations on the same from state', function () {
    $machine = machina()
        ->from(TestState::Pending)
            ->on('start')->to(TestState::Processing)
            ->on('cancel')->to(TestState::Cancelled)
        ->build(TestState::class);

    expect($machine->getOperations(TestState::Pending))->toHaveCount(2);
    expect($machine->findOperation(TestState::Pending, 'start'))->not->toBeNull();
    expect($machine->findOperation(TestState::Pending, 'cancel'))->not->toBeNull();
});

it('keeps backward compat for from()->to()->guard() without operations', function () {
    $machine = machina()
        ->from(TestState::Pending)->to(TestState::Processing)
            ->guard(fn () => false)
        ->build(TestState::class);

    expect($machine->canTransition(TestState::Pending, TestState::Processing))->toBeFalse();
    expect($machine->getOperations(TestState::Pending))->toBe([]);
});

it('requires exactly one target state for operations', function () {
    machina()
        ->from(TestState::Pending)
            ->on('start')->to(TestState::Processing, TestState::Completed);
})->throws(InvalidArgumentException::class, 'Operations accept exactly one target state');
