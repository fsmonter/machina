<?php

declare(strict_types=1);

use Machina\Operation;
use Tests\TestState;

beforeEach(function () {
    $this->machine = machina()
        ->from(TestState::Pending)
            ->on('start')->to(TestState::Processing)
            ->on('cancel')->to(TestState::Cancelled)
                ->guard(fn ($model) => $model?->canCancel ?? true)
        ->from(TestState::Processing)
            ->on('complete')->to(TestState::Completed)
        ->final(TestState::Completed, TestState::Cancelled)
        ->build(TestState::class);
});

it('finds operation by state and name', function () {
    $op = $this->machine->findOperation(TestState::Pending, 'start');

    expect($op)->toBeInstanceOf(Operation::class);
    expect($op->name)->toBe('start');
    expect($op->to)->toBe(TestState::Processing);
});

it('returns null for undefined operation', function () {
    expect($this->machine->findOperation(TestState::Pending, 'nonexistent'))->toBeNull();
});

it('returns null for operation on wrong state', function () {
    expect($this->machine->findOperation(TestState::Processing, 'start'))->toBeNull();
});

it('canSend checks guards', function () {
    $model = new stdClass;
    $model->canCancel = false;

    expect($this->machine->canSend(TestState::Pending, 'cancel', null))->toBeTrue();
    expect($this->machine->canSend(TestState::Pending, 'start'))->toBeTrue();
});

it('canSend returns false for undefined operation', function () {
    expect($this->machine->canSend(TestState::Pending, 'nonexistent'))->toBeFalse();
});

it('canSend returns false when transition guards block operation target', function () {
    $machine = machina()
        ->from(TestState::Pending)->to(TestState::Processing)
            ->guard(fn () => false)
        ->from(TestState::Pending)
            ->on('start')->to(TestState::Processing)
        ->build(TestState::class);

    expect($machine->canTransition(TestState::Pending, TestState::Processing))->toBeFalse();
    expect($machine->canSend(TestState::Pending, 'start'))->toBeFalse();
});

it('lists operations for a state', function () {
    $ops = $this->machine->getOperations(TestState::Pending);

    expect($ops)->toHaveCount(2);
    expect($ops[0]->name)->toBe('start');
    expect($ops[1]->name)->toBe('cancel');
});

it('returns empty array for state with no operations', function () {
    expect($this->machine->getOperations(TestState::Completed))->toBe([]);
});
