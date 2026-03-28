<?php

declare(strict_types=1);

use Machina\StateMachine;
use Machina\StateMachineBuilder;
use Tests\TestIntState;
use Tests\TestState;

it('builds a simple state machine with transition()', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->build();

    expect($sm)->toBeInstanceOf(StateMachine::class);
    expect($sm->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
});

it('builds a machine with multiple transitions', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->transition(from: TestState::Pending, to: TestState::Cancelled)
        ->transition(from: TestState::Processing, to: TestState::Completed)
        ->transition(from: TestState::Processing, to: TestState::Failed)
        ->final(TestState::Completed, TestState::Failed, TestState::Cancelled)
        ->build();

    expect($sm->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
    expect($sm->canTransition(TestState::Pending, TestState::Cancelled))->toBeTrue();
    expect($sm->canTransition(TestState::Processing, TestState::Completed))->toBeTrue();
    expect($sm->canTransition(TestState::Processing, TestState::Failed))->toBeTrue();
    expect($sm->isFinal(TestState::Completed))->toBeTrue();
});

it('prevents duplicate transitions', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->build();

    expect($sm->getTransitions(TestState::Pending))->toHaveCount(1);
});

it('defines final states', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->transition(from: TestState::Processing, to: TestState::Completed)
        ->final(TestState::Completed)
        ->build();

    expect($sm->isFinal(TestState::Completed))->toBeTrue();
    expect($sm->isFinal(TestState::Processing))->toBeFalse();
});

it('ensures final states have empty transitions', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->final(TestState::Completed)
        ->build();

    expect($sm->getTransitions(TestState::Completed))->toEqual([]);
});

it('prevents duplicate final states', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->final(TestState::Completed, TestState::Completed, TestState::Failed)
        ->build();

    $array = $sm->toArray();
    expect($array['final_states'])->toHaveCount(2);
});

it('normalizes keys to strings in final state machine', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->build();

    $array = $sm->toArray();
    expect($array['transitions'])->toHaveKey('pending');
    expect(array_keys($array['transitions']))->each->toBeString();
});

it('handles empty builder with explicit enum class', function () {
    $sm = (new StateMachineBuilder)->build(TestState::class);

    expect($sm)->toBeInstanceOf(StateMachine::class);
    expect($sm->getAllStates())->toEqual([]);
});

it('handles enum values containing colons without key collisions', function () {
    enum ColonState: string
    {
        case PendingReview = 'pending:review';
        case Active = 'active';
        case PendingActive = 'pending';
        case Review = 'review';
    }

    $guardCalled = false;
    $sm = machina()
        ->transition(from: ColonState::PendingReview, to: ColonState::Active,
            guard: function () use (&$guardCalled) {
                $guardCalled = true;

                return true;
            })
        ->transition(from: ColonState::PendingActive, to: ColonState::Review)
        ->build();

    expect($sm->canTransition(ColonState::PendingReview, ColonState::Active))->toBeTrue();
    expect($guardCalled)->toBeTrue();

    expect($sm->canTransition(ColonState::PendingActive, ColonState::Review))->toBeTrue();
    expect($sm->canTransition(ColonState::PendingActive, ColonState::Active))->toBeFalse();
});

it('auto-detects final states without explicit final()', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->transition(from: TestState::Processing, to: TestState::Completed)
        ->build();

    expect($sm->isFinal(TestState::Completed))->toBeTrue();
    expect($sm->isFinal(TestState::Processing))->toBeFalse();
});

it('stores initial state on the compiled machine', function () {
    $sm = machina()
        ->initial(TestState::Pending)
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->build();

    expect($sm->initialState())->toBe(TestState::Pending);
});

it('returns null initial state when not set', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->build();

    expect($sm->initialState())->toBeNull();
});

it('validates initial state enum class consistency', function () {
    machina()
        ->initial(TestIntState::Pending)
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->build();
})->throws(InvalidArgumentException::class, 'All states must be the same enum type');

it('serializes and deserializes initial state', function () {
    $machine = machina()
        ->initial(TestState::Pending)
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->build();

    $array = $machine->toArray();
    expect($array['initial_state'])->toBe('pending');

    $restored = StateMachine::fromArray($array);
    expect($restored->initialState())->toBe(TestState::Pending);
});

it('deserializes without initial state', function () {
    $array = [
        'enum_class' => TestState::class,
        'transitions' => ['pending' => ['processing']],
    ];

    $machine = StateMachine::fromArray($array);
    expect($machine->initialState())->toBeNull();
});

it('builds operations with state()', function () {
    $sm = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('process')->target(TestState::Processing);
            $state->on('cancel')->target(TestState::Cancelled);
        })
        ->build();

    expect($sm->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
    expect($sm->findOperation(TestState::Pending, 'process'))->not->toBeNull();
    expect($sm->findOperation(TestState::Pending, 'cancel'))->not->toBeNull();
});

it('builds operations with guard and action', function () {
    $guardFn = fn () => true;
    $actionFn = fn () => null;

    $sm = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) use ($guardFn, $actionFn) {
            $state->on('process')
                ->target(TestState::Processing)
                ->guard($guardFn)
                ->action($actionFn);
        })
        ->build();

    $op = $sm->findOperation(TestState::Pending, 'process');
    expect($op->guards)->toHaveCount(1);
    expect($op->action)->toBe($actionFn);
});

it('accepts guard as array of closures', function () {
    $sm = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('process')
                ->target(TestState::Processing)
                ->guard([fn () => true, fn () => true]);
        })
        ->build();

    $op = $sm->findOperation(TestState::Pending, 'process');
    expect($op->guards)->toHaveCount(2);
});

it('builds state-bound operations without target', function () {
    $actionFn = fn () => null;

    $sm = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) use ($actionFn) {
            $state->on('notify')->action($actionFn);
        })
        ->transition(from: TestState::Pending, to: TestState::Processing)
        ->build();

    $op = $sm->findOperation(TestState::Pending, 'notify');
    expect($op->to)->toBeNull();
    expect($op->action)->toBe($actionFn);
});

it('rejects duplicate operation names within same state()', function () {
    machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('process')->target(TestState::Processing);
            $state->on('process')->target(TestState::Cancelled);
        });
})->throws(InvalidArgumentException::class, "Duplicate operation 'process' for state pending");

it('allows same operation name on different states', function () {
    $sm = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('cancel')->target(TestState::Cancelled);
        })
        ->state(TestState::Processing, function (Machina\StateBuilder $state) {
            $state->on('cancel')->target(TestState::Cancelled);
        })
        ->build();

    expect($sm->findOperation(TestState::Pending, 'cancel'))->not->toBeNull();
    expect($sm->findOperation(TestState::Processing, 'cancel'))->not->toBeNull();
});

it('rejects transitions from final states', function () {
    machina()
        ->final(TestState::Completed)
        ->transition(from: TestState::Completed, to: TestState::Processing);
})->throws(InvalidArgumentException::class, 'Cannot define transitions from final state completed');

it('rejects state() on final states', function () {
    machina()
        ->final(TestState::Completed)
        ->state(TestState::Completed, function (Machina\StateBuilder $state) {
            $state->on('retry')->target(TestState::Processing);
        });
})->throws(InvalidArgumentException::class, 'Cannot define operations from final state completed');

it('rejects marking a state as final after defining transitions from it', function () {
    machina()
        ->transition(from: TestState::Completed, to: TestState::Processing)
        ->final(TestState::Completed);
})->throws(InvalidArgumentException::class, 'Cannot mark state completed as final after defining transitions from it');

it('rejects marking a state as final after defining operations from it', function () {
    machina()
        ->state(TestState::Completed, function (Machina\StateBuilder $state) {
            $state->on('archive');
        })
        ->final(TestState::Completed);
})->throws(InvalidArgumentException::class, 'Cannot mark state completed as final after defining operations from it');

it('validates enum types in transition()', function () {
    machina()
        ->transition(from: TestState::Pending, to: TestIntState::Pending);
})->throws(InvalidArgumentException::class, 'All states must be the same enum type');

it('validates enum types in state() targets', function () {
    machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('x')->target(TestIntState::Processing);
        });
})->throws(InvalidArgumentException::class, 'All states must be the same enum type');

it('supports transition() with single guard', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing,
            guard: fn () => false)
        ->build();

    expect($sm->canTransition(TestState::Pending, TestState::Processing))->toBeFalse();
});

it('supports transition() with guard array', function () {
    $sm = machina()
        ->transition(from: TestState::Pending, to: TestState::Processing,
            guard: [fn () => true, fn () => false])
        ->build();

    expect($sm->canTransition(TestState::Pending, TestState::Processing))->toBeFalse();
});

it('mixes state() and transition() on the same builder', function () {
    $sm = machina()
        ->state(TestState::Pending, function (Machina\StateBuilder $state) {
            $state->on('process')->target(TestState::Processing);
        })
        ->transition(from: TestState::Processing, to: TestState::Completed)
        ->final(TestState::Completed)
        ->build();

    expect($sm->findOperation(TestState::Pending, 'process'))->not->toBeNull();
    expect($sm->canTransition(TestState::Processing, TestState::Completed))->toBeTrue();
    expect($sm->getOperations(TestState::Processing))->toBe([]);
});
