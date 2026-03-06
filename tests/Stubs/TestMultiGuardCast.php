<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Machina\StateMachineBuilder;
use Machina\StateMachineCast;
use Tests\TestState;

class TestMultiGuardCast extends StateMachineCast
{
    protected string $enum = TestState::class;

    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->from(TestState::Pending)->to(TestState::Processing)
            ->guard(fn (Model $model) => $model->total > 0)
            ->guard(fn (Model $model) => $model->approved === true)
            ->from(TestState::Processing)->to(TestState::Completed)
            ->final(TestState::Completed);
    }
}
