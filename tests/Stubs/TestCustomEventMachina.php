<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Machina\Machina;
use Machina\StateMachineBuilder;
use Tests\TestState;

class TestCustomEventMachina extends Machina
{
    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->transition(from: TestState::Pending, to: TestState::Processing)
            ->transition(from: TestState::Pending, to: TestState::Cancelled)
            ->transition(from: TestState::Processing, to: TestState::Completed)
            ->transition(from: TestState::Processing, to: TestState::Failed)
            ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
    }

    protected function eventClass(): string
    {
        return TestTransitionEvent::class;
    }
}
