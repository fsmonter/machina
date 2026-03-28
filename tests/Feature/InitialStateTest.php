<?php

declare(strict_types=1);

use Tests\Stubs\TestInitialStateMachina;
use Tests\Stubs\TestStateMachine;
use Tests\TestState;
use Workbench\App\Models\TestModel;

it('auto-sets initial state when creating a model without state', function () {
    $model = new class extends TestModel
    {
        protected $stateMachines = [
            'state' => TestInitialStateMachina::class,
        ];
    };
    $model->save();

    expect($model->fresh()->state->value())->toBe(TestState::Pending);
});

it('does not override an explicitly set state', function () {
    $model = new class(['state' => TestState::Processing]) extends TestModel
    {
        protected $stateMachines = [
            'state' => TestInitialStateMachina::class,
        ];
    };
    $model->save();

    expect($model->fresh()->state->value())->toBe(TestState::Processing);
});

it('does not auto-set state when machina has no initial state', function () {
    $model = new class extends TestModel
    {
        protected $stateMachines = [
            'state' => TestStateMachine::class,
        ];
    };
    $model->save();

    expect($model->fresh()->state)->toBeNull();
});
