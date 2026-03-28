<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Machina\Events\StateTransitioned;
use Machina\Exceptions\InvalidStateTransitionException;

abstract class Machina
{
    /** @var array<class-string<static>, StateMachine> */
    private static array $compiledMachines = [];

    abstract public function transitions(): StateMachineBuilder;

    /**
     * @return class-string<BackedEnum>
     */
    public function getEnumClass(): string
    {
        return $this->stateMachine()->enumClass();
    }

    public function stateMachine(): StateMachine
    {
        return self::$compiledMachines[static::class] ??= $this->transitions()->build();
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function performTransition(Model $model, string $column, BackedEnum $oldState, BackedEnum $newState, array $additionalData = []): bool
    {
        $connection = $model->getConnection();

        $connection->transaction(function () use ($connection, $model, $column, $oldState, $newState, $additionalData) {
            if (! $this->stateMachine()->canTransition($oldState, $newState, $model)) {
                throw new InvalidStateTransitionException(
                    "Cannot transition from {$oldState->value} to {$newState->value}"
                );
            }

            $updateData = array_merge($additionalData, [
                $column => $newState->value,
            ]);

            $affected = $model->newQuery()
                ->where($model->getKeyName(), $model->getKey())
                ->where($column, $oldState->value)
                ->update($updateData);

            if ($affected === 0) {
                throw new InvalidStateTransitionException(
                    'State transition failed: state was not updated.'
                );
            }

            $model->forceFill([$column => $newState] + $additionalData)->syncOriginal();

            $connection->afterCommit(function () use ($model, $oldState, $newState) {
                $eventClass = $this->eventClass();
                event(new $eventClass($model, $oldState, $newState));
            });
        });

        return true;
    }

    /**
     * @return class-string<StateTransitioned>
     */
    protected function eventClass(): string
    {
        return StateTransitioned::class;
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function performOperation(Model $model, string $column, BackedEnum $currentState, string $operationName, array $additionalData = []): bool
    {
        $operation = $this->stateMachine()->findOperation($currentState, $operationName);

        if ($operation === null) {
            throw new InvalidStateTransitionException(
                "Operation '{$operationName}' is not defined for current state {$currentState->value}"
            );
        }

        foreach ($operation->guards as $guard) {
            if (! $guard($model)) {
                throw new InvalidStateTransitionException(
                    "Operation '{$operationName}' is blocked by a guard"
                );
            }
        }

        if ($operation->to !== null) {
            $this->performTransition($model, $column, $currentState, $operation->to, $additionalData);
        }

        if ($operation->action !== null) {
            ($operation->action)($model);
        }

        return true;
    }
}
