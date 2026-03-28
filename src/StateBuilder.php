<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use InvalidArgumentException;

class StateBuilder
{
    /** @var list<OperationBuilder> */
    private array $operations = [];

    public function __construct(
        private readonly BackedEnum $state,
    ) {}

    public function on(string $name): OperationBuilder
    {
        foreach ($this->operations as $op) {
            if ($op->toDefinition()['name'] === $name) {
                throw new InvalidArgumentException(
                    "Duplicate operation '{$name}' for state {$this->state->value}"
                );
            }
        }

        $operation = new OperationBuilder($name);
        $this->operations[] = $operation;

        return $operation;
    }

    /**
     * @return list<array{name: string, target: ?BackedEnum, guards: list<\Closure>, action: ?\Closure}>
     */
    public function getOperations(): array
    {
        return array_map(
            fn (OperationBuilder $op) => $op->toDefinition(),
            $this->operations,
        );
    }
}
