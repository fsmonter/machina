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
 * @implements CastsAttributes<State, BackedEnum|State|string|int>
 */
abstract class StateMachineCast implements CastsAttributes
{
    /** @var class-string<BackedEnum> */
    protected string $enum;

    public bool $withoutObjectCaching = true;

    private ?StateMachine $compiledMachine = null;

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
     * @param  State|BackedEnum|string|int|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|int|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof State) {
            return $value->value()->value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    public function stateMachine(): StateMachine
    {
        if ($this->compiledMachine === null) {
            $this->compiledMachine = $this->transitions()->build($this->enum);
        }

        return $this->compiledMachine;
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function performTransition(Model $model, string $column, BackedEnum $oldState, BackedEnum $newState, array $additionalData = []): bool
    {
        if (! $this->stateMachine()->canTransition($oldState, $newState, $model)) {
            throw new InvalidStateTransitionException(
                "Cannot transition from {$oldState->value} to {$newState->value}"
            );
        }

        DB::transaction(function () use ($model, $column, $oldState, $newState, $additionalData) {
            $updateData = array_merge($additionalData, [
                $column => $newState->value,
            ]);

            $affected = $model->newQuery()
                ->where($model->getKeyName(), $model->getKey())
                ->where($column, $oldState->value)
                ->update($updateData);

            if ($affected === 0) {
                throw new InvalidStateTransitionException(
                    'State transition failed: state was modified concurrently'
                );
            }

            $model->fill([$column => $newState] + $additionalData)->syncOriginal();

            DB::afterCommit(function () use ($model, $oldState, $newState) {
                $eventClass = $this->eventClass();
                event(new $eventClass($model, $oldState, $newState));
                $this->afterTransition($model, $oldState, $newState);
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

    protected function afterTransition(Model $model, BackedEnum $oldState, BackedEnum $newState): void {}
}
