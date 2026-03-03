<?php

declare(strict_types=1);

use Machina\StateMachineBuilder;
use Tests\TestIntState;
use Tests\TestState;

it('infers enum class from states without explicit build parameter', function () {
    $sm = (new StateMachineBuilder)
        ->from(TestState::Pending)->to(TestState::Processing)
        ->build();

    expect($sm->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
});

it('throws when mixing enum types in from()', function () {
    expect(fn () => (new StateMachineBuilder)
        ->from(TestState::Pending)->to(TestState::Processing)
        ->from(TestIntState::Pending)->to(TestIntState::Processing)
    )->toThrow(InvalidArgumentException::class, 'All states must be the same enum type');
});

it('throws when mixing enum types in to()', function () {
    expect(fn () => (new StateMachineBuilder)
        ->from(TestState::Pending)->to(TestIntState::Processing)
    )->toThrow(InvalidArgumentException::class, 'All states must be the same enum type');
});

it('throws when mixing enum types in final()', function () {
    expect(fn () => (new StateMachineBuilder)
        ->from(TestState::Pending)->to(TestState::Processing)
        ->final(TestIntState::Completed)
    )->toThrow(InvalidArgumentException::class, 'All states must be the same enum type');
});

it('throws when build() enum class conflicts with inferred class', function () {
    expect(fn () => (new StateMachineBuilder)
        ->from(TestState::Pending)->to(TestState::Processing)
        ->build(TestIntState::class)
    )->toThrow(InvalidArgumentException::class, 'Enum class mismatch');
});

it('throws when building empty builder without enum class', function () {
    expect(fn () => (new StateMachineBuilder)->build())
        ->toThrow(InvalidArgumentException::class, 'Enum class must be provided');
});
