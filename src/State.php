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
        private readonly StateMachineCast $cast,
    ) {}

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function transitionTo(BackedEnum $newState, array $additionalData = []): bool
    {
        return $this->cast->performTransition($this->model, $this->column, $this->value, $newState, $additionalData);
    }

    public function canTransitionTo(BackedEnum $targetState): bool
    {
        return $this->cast->stateMachine()->canTransition($this->value, $targetState, $this->model);
    }

    /**
     * @return list<BackedEnum>
     */
    public function allowedTransitions(): array
    {
        $machine = $this->cast->stateMachine();

        return array_values(array_filter(
            $machine->getTransitions($this->value),
            fn (BackedEnum $target): bool => $machine->canTransition($this->value, $target, $this->model),
        ));
    }

    public function isFinal(): bool
    {
        return $this->cast->stateMachine()->isFinal($this->value);
    }

    public function is(BackedEnum $state): bool
    {
        return $this->value === $state;
    }

    public function value(): BackedEnum
    {
        return $this->value;
    }

    public function stateMachine(): StateMachine
    {
        return $this->cast->stateMachine();
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function send(string $operation, array $additionalData = []): bool
    {
        return $this->cast->performOperation($this->model, $this->column, $this->value, $operation, $additionalData);
    }

    public function canSend(string $operation): bool
    {
        return $this->cast->stateMachine()->canSend($this->value, $operation, $this->model);
    }

    /**
     * @return list<string>
     */
    public function availableOperations(): array
    {
        return array_values(array_map(
            fn (Operation $op) => $op->name,
            array_filter(
                $this->cast->stateMachine()->getOperations($this->value),
                fn (Operation $op) => $this->cast->stateMachine()->canSend($this->value, $op->name, $this->model),
            ),
        ));
    }

    /**
     * @param  list<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (str_starts_with($method, 'can')) {
            return $this->canSend(lcfirst(substr($method, 3)));
        }

        return $this->send($method, $arguments[0] ?? []);
    }

    public function __toString(): string
    {
        return (string) $this->value->value;
    }
}
