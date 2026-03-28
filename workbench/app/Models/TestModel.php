<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Machina\HasStateMachine;
use Machina\Machina;
use Tests\Stubs\TestStateMachine;

class TestModel extends Model
{
    use HasStateMachine;

    protected $table = 'test_models';

    protected $guarded = [];

    /** @var array<string, class-string<Machina>> */
    protected $stateMachines = [
        'state' => TestStateMachine::class,
    ];
}
