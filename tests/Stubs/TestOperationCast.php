<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Machina\StateMachineBuilder;
use Machina\StateMachineCast;
use Tests\TestState;

class TestOperationCast extends StateMachineCast
{
    protected string $enum = TestState::class;

    public static bool $contactCalled = false;

    public static bool $smsCalled = false;

    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->from(TestState::Pending)
                ->on('contact')->to(TestState::Processing)
                    ->do(function (Model $model) {
                        static::$contactCalled = true;
                    })
                ->on('cancel')->to(TestState::Cancelled)

            ->from(TestState::Processing)
                ->on('complete')->to(TestState::Completed)
                    ->guard(fn (Model $model) => $model->total > 0)
                ->on('sms')->do(function (Model $model) {
                    static::$smsCalled = true;
                })
                ->on('fail')->to(TestState::Failed)

            ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
    }
}
