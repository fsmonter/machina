<?php

declare(strict_types=1);

namespace Tests\Stubs;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Machina\StateMachineBuilder;
use Machina\StateMachineCast;
use Tests\TestState;

class TestAfterTransitionCast extends StateMachineCast
{
    protected string $enum = TestState::class;

    public static ?Closure $hook = null;

    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->from(TestState::Pending)->to(TestState::Processing, TestState::Cancelled)
            ->from(TestState::Processing)->to(TestState::Completed, TestState::Failed)
            ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
    }

    protected function afterTransition(Model $model, BackedEnum $oldState, BackedEnum $newState): void
    {
        if (self::$hook) {
            (self::$hook)($model, $oldState, $newState);
        }
    }
}
