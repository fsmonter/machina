<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Machina\Machina;
use Machina\StateMachineBuilder;
use Tests\TestState;

class TestGuardedMachina extends Machina
{
    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->transition(from: TestState::Pending, to: TestState::Processing,
                guard: fn (Model $model) => $model->total > 0)
            ->transition(from: TestState::Pending, to: TestState::Cancelled)
            ->transition(from: TestState::Processing, to: TestState::Completed)
            ->transition(from: TestState::Processing, to: TestState::Failed)
            ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
    }
}
