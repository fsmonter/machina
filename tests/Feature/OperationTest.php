<?php

declare(strict_types=1);

use Machina\Exceptions\InvalidStateTransitionException;
use Tests\Stubs\TestOperationCast;
use Tests\TestState;
use Workbench\App\Models\TestModel;

function createOperationModel(string|TestState $state = TestState::Pending, array $attrs = []): TestModel
{
    $model = new class(array_merge(['state' => $state], $attrs)) extends TestModel
    {
        protected $casts = [
            'state' => TestOperationCast::class,
        ];
    };
    $model->save();

    return $model;
}

beforeEach(function () {
    TestOperationCast::$contactCalled = false;
    TestOperationCast::$smsCalled = false;
});

it('performs a transition via send()', function () {
    $model = createOperationModel();

    $model->state->send('contact');

    expect($model->fresh()->state->value())->toBe(TestState::Processing);
});

it('runs do() closure after transition', function () {
    $model = createOperationModel();

    $model->state->send('contact');

    expect(TestOperationCast::$contactCalled)->toBeTrue();
});

it('runs do() without transition for state-bound operations', function () {
    $model = createOperationModel(TestState::Processing, ['total' => 10]);

    $model->state->send('sms');

    expect(TestOperationCast::$smsCalled)->toBeTrue();
    expect($model->fresh()->state->value())->toBe(TestState::Processing);
});

it('throws when guard blocks operation', function () {
    $model = createOperationModel(TestState::Processing, ['total' => 0]);

    expect(fn () => $model->state->send('complete'))
        ->toThrow(InvalidStateTransitionException::class, 'blocked by a guard');
});

it('checks canSend() correctly', function () {
    $model = createOperationModel(TestState::Processing, ['total' => 10]);

    expect($model->state->canSend('complete'))->toBeTrue();
    expect($model->state->canSend('sms'))->toBeTrue();
    expect($model->state->canSend('contact'))->toBeFalse();
});

it('returns available operations filtered by guards', function () {
    $model = createOperationModel(TestState::Processing, ['total' => 0]);

    $available = $model->state->availableOperations();

    expect($available)->toContain('sms');
    expect($available)->toContain('fail');
    expect($available)->not->toContain('complete');
});

it('supports __call for send', function () {
    $model = createOperationModel();

    $model->state->contact();

    expect($model->fresh()->state->value())->toBe(TestState::Processing);
    expect(TestOperationCast::$contactCalled)->toBeTrue();
});

it('supports __call for canSend', function () {
    $model = createOperationModel(TestState::Processing, ['total' => 10]);

    expect($model->state->canComplete())->toBeTrue();
    expect($model->state->canSms())->toBeTrue();
    expect($model->state->canContact())->toBeFalse();
});

it('throws for undefined operation', function () {
    $model = createOperationModel();

    expect(fn () => $model->state->send('nonexistent'))
        ->toThrow(InvalidStateTransitionException::class, "not defined for state");
});

it('throws for operation on wrong state', function () {
    $model = createOperationModel();

    expect(fn () => $model->state->send('complete'))
        ->toThrow(InvalidStateTransitionException::class, "not defined for state");
});
