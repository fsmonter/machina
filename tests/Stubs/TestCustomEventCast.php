<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Machina\StateMachineBuilder;
use Machina\StateMachineCast;
use Tests\TestState;

class TestCustomEventCast extends StateMachineCast
{
    protected string $enum = TestState::class;

    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->from(TestState::Pending)->to(TestState::Processing, TestState::Cancelled)
            ->from(TestState::Processing)->to(TestState::Completed, TestState::Failed)
            ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
    }

    protected function eventClass(): string
    {
        return TestTransitionEvent::class;
    }
}
