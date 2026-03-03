<?php

declare(strict_types=1);

namespace Tests\Stubs;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;

class TestTransitionEvent
{
    public function __construct(
        public readonly Model $model,
        public readonly BackedEnum $oldState,
        public readonly BackedEnum $newState,
    ) {}
}
