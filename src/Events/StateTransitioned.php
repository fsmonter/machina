<?php

declare(strict_types=1);

namespace Machina\Events;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;

class StateTransitioned
{
    public function __construct(
        public readonly Model $model,
        public readonly BackedEnum $oldState,
        public readonly BackedEnum $newState,
    ) {}
}
