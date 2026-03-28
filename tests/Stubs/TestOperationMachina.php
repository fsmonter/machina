<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Machina\Machina;
use Machina\StateBuilder;
use Machina\StateMachineBuilder;
use Tests\TestState;

class TestOperationMachina extends Machina
{
    public static bool $contactCalled = false;

    public static bool $smsCalled = false;

    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->state(TestState::Pending, function (StateBuilder $state) {
                $state->on('contact')
                    ->target(TestState::Processing)
                    ->action(function (Model $model) {
                        static::$contactCalled = true;
                    });
                $state->on('cancel')->target(TestState::Cancelled);
            })
            ->state(TestState::Processing, function (StateBuilder $state) {
                $state->on('complete')
                    ->target(TestState::Completed)
                    ->guard(fn (Model $model) => $model->total > 0);
                $state->on('sms')
                    ->action(function (Model $model) {
                        static::$smsCalled = true;
                    });
                $state->on('fail')->target(TestState::Failed);
            })
            ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
    }
}
