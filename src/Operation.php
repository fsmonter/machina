<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use Closure;

final class Operation
{
    /**
     * @param  list<Closure>  $guards
     */
    public function __construct(
        public readonly string $name,
        public readonly BackedEnum $from,
        public readonly ?BackedEnum $to,
        public readonly array $guards = [],
        public readonly ?Closure $do = null,
    ) {}
}
