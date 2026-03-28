<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use Closure;

class OperationBuilder
{
    private ?BackedEnum $target = null;

    /** @var list<Closure> */
    private array $guards = [];

    private ?Closure $action = null;

    public function __construct(
        private readonly string $name,
    ) {}

    public function target(BackedEnum $state): self
    {
        $this->target = $state;

        return $this;
    }

    /**
     * @param  Closure|list<Closure>  $guard
     */
    public function guard(Closure|array $guard): self
    {
        if ($guard instanceof Closure) {
            $this->guards[] = $guard;
        } else {
            $this->guards = array_merge($this->guards, $guard);
        }

        return $this;
    }

    public function action(Closure $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @return array{name: string, target: ?BackedEnum, guards: list<Closure>, action: ?Closure}
     */
    public function toDefinition(): array
    {
        return [
            'name' => $this->name,
            'target' => $this->target,
            'guards' => $this->guards,
            'action' => $this->action,
        ];
    }
}
