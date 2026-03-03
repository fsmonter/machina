<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Maquina\Concerns\HasStateMachine;
use Maquina\StateMachineBuilder;
use Tests\TestState;

class TestModel extends Model
{
    use HasStateMachine;

    protected $table = 'test_models';

    protected $guarded = [];

    protected $casts = [
        'state' => TestState::class,
    ];

    protected function defineStateMachine(): StateMachineBuilder
    {
        return machine()
            ->from(TestState::Pending)->to(TestState::Processing, TestState::Cancelled)
            ->from(TestState::Processing)->to(TestState::Completed, TestState::Failed)
            ->final(TestState::Completed, TestState::Failed, TestState::Cancelled);
    }
}
