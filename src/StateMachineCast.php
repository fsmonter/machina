<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Machina\Events\StateTransitioned;
use Machina\Exceptions\InvalidStateTransitionException;

/**
 * @implements CastsAttributes<State, mixed>
 */
abstract class StateMachineCast implements CastsAttributes
{
    /** @var class-string<BackedEnum> */
    protected string $enum;

    public bool $withoutObjectCaching = true;

    /** @var array<class-string<self>, StateMachine> */
    private static array $compiledMachines = [];

    abstract public function transitions(): StateMachineBuilder;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?State
    {
        if ($value === null) {
            return null;
        }

        /** @var int|string $value */
        $enum = ($this->enum)::from($value);

        return new State($enum, $model, $key, $this);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|int|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof State) {
            $value = $value->value();
        }

        if (! $value instanceof BackedEnum) {
            throw new \InvalidArgumentException(
                "Value must be a {$this->enum} enum instance, got ".get_debug_type($value)
            );
        }

        if (! $value instanceof $this->enum) {
            throw new \InvalidArgumentException(
                "Value must be a {$this->enum} enum instance, got ".$value::class
            );
        }

        return $value->value;
    }

    public function stateMachine(): StateMachine
    {
        return self::$compiledMachines[static::class] ??= $this->transitions()->build($this->enum);
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function performTransition(Model $model, string $column, BackedEnum $oldState, BackedEnum $newState, array $additionalData = []): bool
    {
        DB::transaction(function () use ($model, $column, $oldState, $newState, $additionalData) {
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

            $model->fill([$column => $newState] + $additionalData)->syncOriginal();

            DB::afterCommit(function () use ($model, $oldState, $newState) {
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
                "Operation '{$operationName}' is not defined for state {$currentState->value}"
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

        if ($operation->do !== null) {
            ($operation->do)($model);
        }

        return true;
    }
}
