<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Maquina\Concerns\HasStateMachine;
use Maquina\Exceptions\InvalidStateTransitionException;
use Maquina\StateMachineBuilder;
use Tests\TestState;

it('allows transition when guard passes', function () {
    $model = createGuardedModel(total: 100);

    $model->transitionTo(TestState::Processing);

    expect($model->fresh()->state)->toBe(TestState::Processing);
});

it('blocks transition when guard fails', function () {
    $model = createGuardedModel(total: 0);

    expect($model->canTransitionTo(TestState::Processing))->toBeFalse();

    expect(fn () => $model->transitionTo(TestState::Processing))
        ->toThrow(InvalidStateTransitionException::class);
});

it('filters getAllowedTransitions based on guards', function () {
    $model = createGuardedModel(total: 0);

    $allowed = $model->getAllowedTransitions();

    expect($allowed)->toContain(TestState::Cancelled);
    expect($allowed)->not->toContain(TestState::Processing);
});

it('supports multiple guards on the same transition', function () {
    $model = createMultiGuardModel(total: 100, approved: false);

    expect($model->canTransitionTo(TestState::Processing))->toBeFalse();

    $model = createMultiGuardModel(total: 100, approved: true);

    expect($model->canTransitionTo(TestState::Processing))->toBeTrue();
});

it('applies guards only to specified transitions', function () {
    $model = createGuardedModel(total: 0);

    expect($model->canTransitionTo(TestState::Processing))->toBeFalse();
    expect($model->canTransitionTo(TestState::Cancelled))->toBeTrue();
});

function createGuardedModel(int $total): Model
{
    $model = new class (['state' => TestState::Pending, 'total' => $total]) extends \Tests\Models\TestModel {
        protected function defineStateMachine(): StateMachineBuilder
        {
            return machine()
                ->from(TestState::Pending)->to(TestState::Processing)
                    ->guard(fn (Model $model) => $model->total > 0)
                ->from(TestState::Pending)->to(TestState::Cancelled)
                ->from(TestState::Processing)->to(TestState::Completed, TestState::Failed)
                ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
        }
    };
    $model->save();

    return $model;
}

function createMultiGuardModel(int $total, bool $approved): Model
{
    $model = new class (['state' => TestState::Pending, 'total' => $total, 'approved' => $approved]) extends \Tests\Models\TestModel {
        protected function defineStateMachine(): StateMachineBuilder
        {
            return machine()
                ->from(TestState::Pending)->to(TestState::Processing)
                    ->guard(fn (Model $model) => $model->total > 0)
                    ->guard(fn (Model $model) => $model->approved === true)
                ->from(TestState::Processing)->to(TestState::Completed)
                ->final(TestState::Completed);
        }
    };
    $model->save();

    return $model;
}
