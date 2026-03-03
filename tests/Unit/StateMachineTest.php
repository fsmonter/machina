<?php

declare(strict_types=1);

use Machina\StateMachine;
use Tests\TestState;

beforeEach(function () {
    $this->transitions = [
        'pending' => [TestState::Processing, TestState::Cancelled],
        'processing' => [TestState::Completed, TestState::Failed],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    $this->finalStates = [TestState::Completed, TestState::Failed, TestState::Cancelled];

    $this->stateMachine = new StateMachine(
        TestState::class,
        $this->transitions,
        $this->finalStates
    );
});

it('can check valid transitions', function () {
    expect($this->stateMachine->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
    expect($this->stateMachine->canTransition(TestState::Pending, TestState::Cancelled))->toBeTrue();
    expect($this->stateMachine->canTransition(TestState::Processing, TestState::Completed))->toBeTrue();
    expect($this->stateMachine->canTransition(TestState::Processing, TestState::Failed))->toBeTrue();
});

it('can check invalid transitions', function () {
    expect($this->stateMachine->canTransition(TestState::Pending, TestState::Completed))->toBeFalse();
    expect($this->stateMachine->canTransition(TestState::Completed, TestState::Processing))->toBeFalse();
    expect($this->stateMachine->canTransition(TestState::Failed, TestState::Completed))->toBeFalse();
    expect($this->stateMachine->canTransition(TestState::Cancelled, TestState::Processing))->toBeFalse();
    expect($this->stateMachine->canTransition(TestState::Cancelled, TestState::Pending))->toBeFalse();
});

it('can get transitions from a state', function () {
    $transitions = $this->stateMachine->getTransitions(TestState::Pending);
    expect($transitions)->toEqual([TestState::Processing, TestState::Cancelled]);

    $transitions = $this->stateMachine->getTransitions(TestState::Processing);
    expect($transitions)->toEqual([TestState::Completed, TestState::Failed]);

    $transitions = $this->stateMachine->getTransitions(TestState::Completed);
    expect($transitions)->toEqual([]);
});

it('caches transition lookups', function () {
    $transitions1 = $this->stateMachine->getTransitions(TestState::Pending);
    $transitions2 = $this->stateMachine->getTransitions(TestState::Pending);

    expect($transitions1)->toEqual($transitions2);
});

it('can check final states with explicit final states', function () {
    expect($this->stateMachine->isFinal(TestState::Completed))->toBeTrue();
    expect($this->stateMachine->isFinal(TestState::Failed))->toBeTrue();
    expect($this->stateMachine->isFinal(TestState::Cancelled))->toBeTrue();
    expect($this->stateMachine->isFinal(TestState::Pending))->toBeFalse();
    expect($this->stateMachine->isFinal(TestState::Processing))->toBeFalse();
});

it('can check final states without explicit final states', function () {
    $stateMachine = new StateMachine(TestState::class, $this->transitions);

    expect($stateMachine->isFinal(TestState::Completed))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Failed))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Cancelled))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Pending))->toBeFalse();
    expect($stateMachine->isFinal(TestState::Processing))->toBeFalse();
});

it('can get source states for a target state', function () {
    $sources = $this->stateMachine->getSourceStates(TestState::Processing);
    expect($sources)->toEqual([TestState::Pending]);

    $sources = $this->stateMachine->getSourceStates(TestState::Completed);
    expect($sources)->toEqual([TestState::Processing]);

    $sources = $this->stateMachine->getSourceStates(TestState::Failed);
    expect($sources)->toEqual([TestState::Processing]);

    $sources = $this->stateMachine->getSourceStates(TestState::Cancelled);
    expect($sources)->toEqual([TestState::Pending]);
});

it('can get all states', function () {
    $allStates = $this->stateMachine->getAllStates();

    expect($allStates)->toHaveCount(5);
    expect($allStates)->toContain(TestState::Pending);
    expect($allStates)->toContain(TestState::Processing);
    expect($allStates)->toContain(TestState::Completed);
    expect($allStates)->toContain(TestState::Failed);
    expect($allStates)->toContain(TestState::Cancelled);
});

it('can convert to array', function () {
    $array = $this->stateMachine->toArray();

    expect($array)->toHaveKeys(['enum_class', 'transitions', 'final_states']);
    expect($array['enum_class'])->toEqual(TestState::class);
    expect($array['transitions'])->toEqual([
        'pending' => ['processing', 'cancelled'],
        'processing' => ['completed', 'failed'],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ]);
    expect($array['final_states'])->toEqual(['completed', 'failed', 'cancelled']);
});

it('can create from array', function () {
    $array = [
        'enum_class' => TestState::class,
        'transitions' => [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['completed', 'failed'],
            'completed' => [],
            'failed' => [],
            'cancelled' => [],
        ],
        'final_states' => ['completed', 'failed', 'cancelled'],
    ];

    $stateMachine = StateMachine::fromArray($array);

    expect($stateMachine->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
    expect($stateMachine->canTransition(TestState::Pending, TestState::Completed))->toBeFalse();
    expect($stateMachine->isFinal(TestState::Completed))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Pending))->toBeFalse();
});

it('can create from array without final states', function () {
    $array = [
        'enum_class' => TestState::class,
        'transitions' => [
            'pending' => ['processing'],
            'processing' => ['completed'],
            'completed' => [],
        ],
    ];

    $stateMachine = StateMachine::fromArray($array);

    expect($stateMachine->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Completed))->toBeTrue();
});

it('handles states with no outgoing transitions', function () {
    $sources = $this->stateMachine->getSourceStates(TestState::Pending);
    expect($sources)->toEqual([]);
});

it('handles non-existent state transitions', function () {
    $transitions = $this->stateMachine->getTransitions(TestState::Failed);
    expect($transitions)->toEqual([]);
});
