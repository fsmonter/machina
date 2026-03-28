<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Machina\Machina;
use Machina\StateMachineBuilder;
use Tests\TestState;

class TestMultiGuardMachina extends Machina
{
    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->transition(from: TestState::Pending, to: TestState::Processing,
                guard: [
                    fn (Model $model) => $model->total > 0,
                    fn (Model $model) => $model->approved === true,
                ])
            ->transition(from: TestState::Processing, to: TestState::Completed)
            ->final(TestState::Completed);
    }
}
