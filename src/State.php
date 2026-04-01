<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Stringable;

class State implements Stringable
{
    public function __construct(
        private readonly BackedEnum $value,
        private readonly Model $model,
        private readonly string $column,
        private readonly Machina $machina,
    ) {}

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function transitionTo(BackedEnum $newState, array $additionalData = []): bool
    {
        return $this->machina->performTransition($this->model, $this->column, $this->value, $newState, $additionalData);
    }

    public function canTransitionTo(BackedEnum $targetState): bool
    {
        return $this->machina->stateMachine()->canTransition($this->value, $targetState, $this->model);
    }

    /**
     * @return list<BackedEnum>
     */
    public function allowedTransitions(): array
    {
        $machine = $this->machina->stateMachine();

        return array_values(array_filter(
            $machine->getTransitions($this->value),
            fn (BackedEnum $target): bool => $machine->canTransition($this->value, $target, $this->model),
        ));
    }

    public function isFinal(): bool
    {
        return $this->machina->stateMachine()->isFinal($this->value);
    }

    public function is(BackedEnum $state): bool
    {
        return $this->value === $state;
    }

    public function current(): BackedEnum
    {
        return $this->value;
    }

    public function stateMachine(): StateMachine
    {
        return $this->machina->stateMachine();
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function send(string $operation, array $additionalData = []): bool
    {
        return $this->machina->performOperation($this->model, $this->column, $this->value, $operation, $additionalData);
    }

    public function canSend(string $operation): bool
    {
        return $this->machina->stateMachine()->canSend($this->value, $operation, $this->model);
    }

    /**
     * @return list<string>
     */
    public function availableOperations(): array
    {
        return array_values(array_map(
            fn (Operation $op) => $op->name,
            array_filter(
                $this->machina->stateMachine()->getOperations($this->value),
                fn (Operation $op) => $this->machina->stateMachine()->canSend($this->value, $op->name, $this->model),
            ),
        ));
    }

    /**
     * @param  array{0?: array<string, mixed>}  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $isCanCheck = preg_match('/^can[A-Z]/', $method)
            && ! $this->stateMachine()->findOperation($this->value, $method);

        if ($isCanCheck) {
            return $this->canSend(lcfirst(substr($method, 3)));
        }

        /** @var array<string, mixed> $additionalData */
        $additionalData = $arguments[0] ?? [];

        return $this->send($method, $additionalData);
    }

    public function __toString(): string
    {
        return (string) $this->value->value;
    }
}
