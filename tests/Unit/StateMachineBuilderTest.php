<?php

declare(strict_types=1);

use Machina\StateMachine;
use Machina\StateMachineBuilder;
use Tests\TestState;

it('can build a simple state machine', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Pending)->to(TestState::Processing)
        ->from(TestState::Processing)->to(TestState::Completed)
        ->build(TestState::class);

    expect($stateMachine)->toBeInstanceOf(StateMachine::class);
    expect($stateMachine->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
    expect($stateMachine->canTransition(TestState::Processing, TestState::Completed))->toBeTrue();
});

it('can build a complex state machine with multiple transitions', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Pending)->to(TestState::Processing, TestState::Cancelled)
        ->from(TestState::Processing)->to(TestState::Completed, TestState::Failed)
        ->final(TestState::Completed, TestState::Failed, TestState::Cancelled)
        ->build(TestState::class);

    expect($stateMachine->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
    expect($stateMachine->canTransition(TestState::Pending, TestState::Cancelled))->toBeTrue();
    expect($stateMachine->canTransition(TestState::Processing, TestState::Completed))->toBeTrue();
    expect($stateMachine->canTransition(TestState::Processing, TestState::Failed))->toBeTrue();

    expect($stateMachine->isFinal(TestState::Completed))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Failed))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Cancelled))->toBeTrue();
});

it('can add multiple transitions to the same source state', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Pending)->to(TestState::Processing)
        ->from(TestState::Pending)->to(TestState::Cancelled)
        ->build(TestState::class);

    $transitions = $stateMachine->getTransitions(TestState::Pending);
    expect($transitions)->toHaveCount(2);
    expect($transitions)->toContain(TestState::Processing);
    expect($transitions)->toContain(TestState::Cancelled);
});

it('prevents duplicate transitions', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Pending)
        ->to(TestState::Processing, TestState::Processing, TestState::Cancelled)
        ->build(TestState::class);

    $transitions = $stateMachine->getTransitions(TestState::Pending);
    expect($transitions)->toHaveCount(2);
    expect($transitions)->toContain(TestState::Processing);
    expect($transitions)->toContain(TestState::Cancelled);
});

it('throws exception when calling to before from', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Must call from() before to()');

    $builder = new StateMachineBuilder;

    $builder->to(TestState::Processing);
});

it('can define final states', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Pending)->to(TestState::Processing)
        ->from(TestState::Processing)->to(TestState::Completed)
        ->final(TestState::Completed)
        ->build(TestState::class);

    expect($stateMachine->isFinal(TestState::Completed))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Processing))->toBeFalse();
});

it('ensures final states have empty transitions', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Completed)->to(TestState::Processing)
        ->final(TestState::Completed)
        ->build(TestState::class);

    $transitions = $stateMachine->getTransitions(TestState::Completed);
    expect($transitions)->toEqual([]);
});

it('prevents duplicate final states', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Pending)->to(TestState::Processing)
        ->final(TestState::Completed, TestState::Completed, TestState::Failed)
        ->build(TestState::class);

    $array = $stateMachine->toArray();
    expect($array['final_states'])->toHaveCount(2);
    expect($array['final_states'])->toContain('completed');
    expect($array['final_states'])->toContain('failed');
});

it('can chain method calls fluently', function () {
    $builder = new StateMachineBuilder;

    expect($builder->from(TestState::Pending))->toBe($builder);
    expect($builder->to(TestState::Processing))->toBe($builder);
    expect($builder->final(TestState::Completed))->toBe($builder);
});

it('normalizes keys to strings in final state machine', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Pending)->to(TestState::Processing)
        ->build(TestState::class);

    $array = $stateMachine->toArray();
    expect($array['transitions'])->toHaveKey('pending');
    expect(array_keys($array['transitions']))->each->toBeString();
});

it('handles empty builder with explicit enum class', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder->build(TestState::class);

    expect($stateMachine)->toBeInstanceOf(StateMachine::class);
    expect($stateMachine->getAllStates())->toEqual([]);
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
        ->from(ColonState::PendingReview)->to(ColonState::Active)
            ->guard(function () use (&$guardCalled) {
                $guardCalled = true;

                return true;
            })
        ->from(ColonState::PendingActive)->to(ColonState::Review)
        ->build();

    expect($sm->canTransition(ColonState::PendingReview, ColonState::Active))->toBeTrue();
    expect($guardCalled)->toBeTrue();

    expect($sm->canTransition(ColonState::PendingActive, ColonState::Review))->toBeTrue();
    expect($sm->canTransition(ColonState::PendingActive, ColonState::Active))->toBeFalse();
});

it('can build state machine without explicit final states', function () {
    $builder = new StateMachineBuilder;
    $stateMachine = $builder
        ->from(TestState::Pending)->to(TestState::Processing)
        ->from(TestState::Processing)->to(TestState::Completed)
        ->build(TestState::class);

    expect($stateMachine->isFinal(TestState::Completed))->toBeTrue();
    expect($stateMachine->isFinal(TestState::Processing))->toBeFalse();
});
