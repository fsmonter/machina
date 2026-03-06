<?php

declare(strict_types=1);

use Machina\Exceptions\InvalidStateTransitionException;
use Tests\Stubs\TestGuardedCast;
use Tests\Stubs\TestMultiGuardCast;
use Tests\TestState;
use Workbench\App\Models\TestModel;

function createGuardedModel(int $total): TestModel
{
    $model = new class(['state' => TestState::Pending, 'total' => $total]) extends TestModel
    {
        protected $casts = [
            'state' => TestGuardedCast::class,
        ];
    };
    $model->save();

    return $model;
}

function createMultiGuardModel(int $total, bool $approved): TestModel
{
    $model = new class(['state' => TestState::Pending, 'total' => $total, 'approved' => $approved]) extends TestModel
    {
        protected $casts = [
            'state' => TestMultiGuardCast::class,
        ];
    };
    $model->save();

    return $model;
}

it('allows transition when guard passes', function () {
    $model = createGuardedModel(total: 100);

    $model->state->transitionTo(TestState::Processing);

    expect($model->fresh()->state->value())->toBe(TestState::Processing);
});

it('blocks transition when guard fails', function () {
    $model = createGuardedModel(total: 0);

    expect($model->state->canTransitionTo(TestState::Processing))->toBeFalse();

    expect(fn () => $model->state->transitionTo(TestState::Processing))
        ->toThrow(InvalidStateTransitionException::class);
});

it('filters allowedTransitions based on guards', function () {
    $model = createGuardedModel(total: 0);

    $allowed = $model->state->allowedTransitions();

    expect($allowed)->toContain(TestState::Cancelled);
    expect($allowed)->not->toContain(TestState::Processing);
});

it('supports multiple guards on the same transition', function () {
    $model = createMultiGuardModel(total: 100, approved: false);

    expect($model->state->canTransitionTo(TestState::Processing))->toBeFalse();

    $model = createMultiGuardModel(total: 100, approved: true);

    expect($model->state->canTransitionTo(TestState::Processing))->toBeTrue();
});

it('applies guards only to specified transitions', function () {
    $model = createGuardedModel(total: 0);

    expect($model->state->canTransitionTo(TestState::Processing))->toBeFalse();
    expect($model->state->canTransitionTo(TestState::Cancelled))->toBeTrue();
});
