<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Machina\Machina;
use Machina\StateMachineBuilder;
use Tests\TestState;

class TestOperationMachina extends Machina
{
    public static bool $contactCalled = false;

    public static bool $smsCalled = false;

    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->on('contact',
                from: TestState::Pending,
                to: TestState::Processing,
                action: function (Model $model) {
                    static::$contactCalled = true;
                })
            ->on('cancel', from: TestState::Pending, to: TestState::Cancelled)
            ->on('complete',
                from: TestState::Processing,
                to: TestState::Completed,
                guard: fn (Model $model) => $model->total > 0)
            ->on('sms',
                from: TestState::Processing,
                action: function (Model $model) {
                    static::$smsCalled = true;
                })
            ->on('fail', from: TestState::Processing, to: TestState::Failed)
            ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
    }
}
