<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Tests\Stubs\TestStateMachineCast;

class TestModel extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];

    protected $casts = [
        'state' => TestStateMachineCast::class,
    ];
}
