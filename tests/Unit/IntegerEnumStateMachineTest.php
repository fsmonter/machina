<?php

declare(strict_types=1);

use Machina\StateMachine;
use Tests\TestIntState;

it('supports integer-backed enums via builder', function () {
    $sm = machina()
        ->transition(from: TestIntState::Pending, to: TestIntState::Processing)
        ->transition(from: TestIntState::Processing, to: TestIntState::Completed)
        ->transition(from: TestIntState::Processing, to: TestIntState::Failed)
        ->final(TestIntState::Completed, TestIntState::Failed)
        ->build();

    expect($sm->canTransition(TestIntState::Pending, TestIntState::Processing))->toBeTrue();
    expect($sm->canTransition(TestIntState::Processing, TestIntState::Completed))->toBeTrue();
    expect($sm->canTransition(TestIntState::Pending, TestIntState::Completed))->toBeFalse();
    expect($sm->isFinal(TestIntState::Completed))->toBeTrue();
    expect($sm->isFinal(TestIntState::Pending))->toBeFalse();
});

it('serializes and deserializes integer-backed enums', function () {
    $sm = machina()
        ->transition(from: TestIntState::Pending, to: TestIntState::Processing)
        ->transition(from: TestIntState::Processing, to: TestIntState::Completed)
        ->transition(from: TestIntState::Processing, to: TestIntState::Failed)
        ->final(TestIntState::Completed, TestIntState::Failed)
        ->build();

    $array = $sm->toArray();

    expect($array['transitions'])->toHaveKey(0);
    expect($array['transitions'][0])->toBe([1]);
    expect($array['transitions'][1])->toBe([2, 3]);
    expect($array['final_states'])->toBe([2, 3]);

    $restored = StateMachine::fromArray($array);

    expect($restored->canTransition(TestIntState::Pending, TestIntState::Processing))->toBeTrue();
    expect($restored->canTransition(TestIntState::Processing, TestIntState::Completed))->toBeTrue();
    expect($restored->canTransition(TestIntState::Pending, TestIntState::Completed))->toBeFalse();
    expect($restored->isFinal(TestIntState::Completed))->toBeTrue();
});

it('gets transitions for integer-backed enums', function () {
    $sm = machina()
        ->transition(from: TestIntState::Pending, to: TestIntState::Processing)
        ->transition(from: TestIntState::Processing, to: TestIntState::Completed)
        ->transition(from: TestIntState::Processing, to: TestIntState::Failed)
        ->build();

    expect($sm->getTransitions(TestIntState::Pending))->toBe([TestIntState::Processing]);
    expect($sm->getTransitions(TestIntState::Processing))->toBe([TestIntState::Completed, TestIntState::Failed]);
    expect($sm->getTransitions(TestIntState::Completed))->toBe([]);
});

it('gets source states for integer-backed enums', function () {
    $sm = machina()
        ->transition(from: TestIntState::Pending, to: TestIntState::Processing)
        ->transition(from: TestIntState::Processing, to: TestIntState::Completed)
        ->build();

    expect($sm->getSourceStates(TestIntState::Processing))->toBe([TestIntState::Pending]);
    expect($sm->getSourceStates(TestIntState::Completed))->toBe([TestIntState::Processing]);
});

it('gets all states for integer-backed enums', function () {
    $sm = machina()
        ->transition(from: TestIntState::Pending, to: TestIntState::Processing)
        ->transition(from: TestIntState::Processing, to: TestIntState::Completed)
        ->build();

    $allStates = $sm->getAllStates();

    expect($allStates)->toHaveCount(3);
    expect($allStates)->toContain(TestIntState::Pending);
    expect($allStates)->toContain(TestIntState::Processing);
    expect($allStates)->toContain(TestIntState::Completed);
});
