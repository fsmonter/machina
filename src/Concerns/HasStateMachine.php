<?php

declare(strict_types=1);

namespace Maquina\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maquina\Events\StateTransitioned;
use Maquina\Exceptions\InvalidStateTransitionException;
use Maquina\StateMachine;
use Maquina\StateMachineBuilder;

trait HasStateMachine
{
    /**
     * Cached state machine instance
     */
    private ?StateMachine $stateMachine = null;

    /**
     * Define the state machine transitions - implement this in your model
     */
    abstract protected function defineStateMachine(): StateMachineBuilder;

    /**
     * Get the state attribute name (override if different from 'state')
     */
    protected function getStateColumn(): string
    {
        return 'state';
    }

    /**
     * Get the enum class for the state attribute
     *
     * @return class-string<BackedEnum>
     */
    protected function getStateEnumClass(): string
    {
        $casts = $this->getCasts();
        $stateAttribute = $this->getStateColumn();

        if (! isset($casts[$stateAttribute])) {
            throw new InvalidArgumentException("State attribute '{$stateAttribute}' must be cast to an enum");
        }

        /** @var class-string<BackedEnum> */
        return $casts[$stateAttribute];
    }

    /**
     * Get the compiled state machine instance
     */
    public function getStateMachine(): StateMachine
    {
        if ($this->stateMachine === null) {
            $enumClass = $this->getStateEnumClass();
            $builder = $this->defineStateMachine();
            $this->stateMachine = $builder->build($enumClass);
        }

        return $this->stateMachine;
    }

    /**
     * Transition to a new state with validation and side effects
     *
     * @param  array<string, mixed>  $additionalData
     */
    public function transitionTo(BackedEnum $newState, array $additionalData = []): bool
    {
        $currentState = $this->getCurrentState();

        if (! $this->getStateMachine()->canTransition($currentState, $newState, $this)) {
            throw new InvalidStateTransitionException(
                "Cannot transition from {$currentState->value} to {$newState->value}"
            );
        }

        $stateColumn = $this->getStateColumn();
        $oldState = $currentState;

        return DB::transaction(function () use ($stateColumn, $oldState, $newState, $additionalData) {
            $updateData = array_merge($additionalData, [
                $stateColumn => $newState->value,
            ]);

            $affected = $this->newQuery()
                ->where($this->getKeyName(), $this->getKey())
                ->where($stateColumn, $oldState->value)
                ->update($updateData);

            if ($affected === 0) {
                throw new InvalidStateTransitionException(
                    "State transition failed: state was modified concurrently"
                );
            }

            $this->fill([$stateColumn => $newState] + $additionalData)->syncOriginal();

            $this->fireTransitionEvent($oldState, $newState);
            $this->afterTransition($oldState, $newState);

            return true;
        });
    }

    /**
     * Check if transition to target state is allowed
     */
    public function canTransitionTo(BackedEnum $targetState): bool
    {
        $currentState = $this->getCurrentState();

        return $this->getStateMachine()->canTransition($currentState, $targetState, $this);
    }

    /**
     * Get all allowed transitions from current state
     *
     * @return list<BackedEnum>
     */
    public function getAllowedTransitions(): array
    {
        $currentState = $this->getCurrentState();

        return array_values(array_filter(
            $this->getStateMachine()->getTransitions($currentState),
            fn (BackedEnum $target): bool => $this->getStateMachine()->canTransition($currentState, $target, $this),
        ));
    }

    /**
     * Check if current state is final (no outgoing transitions)
     */
    public function isInFinalState(): bool
    {
        return $this->getStateMachine()->isFinal($this->getCurrentState());
    }

    protected function getCurrentState(): BackedEnum
    {
        $state = $this->getAttribute($this->getStateColumn());

        if (! $state instanceof BackedEnum) {
            throw new InvalidStateTransitionException(
                "State attribute '{$this->getStateColumn()}' is null or not a valid state"
            );
        }

        return $state;
    }

    /**
     * Fire the appropriate transition event
     */
    protected function fireTransitionEvent(BackedEnum $oldState, BackedEnum $newState): void
    {
        $eventClass = $this->getTransitionEventClass();

        event(new $eventClass($this, $oldState, $newState));
    }

    /**
     * Get the event class name for transitions
     * Override in model to fire events on state transitions
     *
     * @return class-string<StateTransitioned>
     */
    protected function getTransitionEventClass(): string
    {
        return StateTransitioned::class;
    }

    /**
     * Hook called after a successful state transition
     * Override in models to implement custom post-transition logic
     */
    protected function afterTransition(BackedEnum $oldState, BackedEnum $newState): void {}

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereState(Builder $query, BackedEnum ...$states): void
    {
        $values = array_map(fn (BackedEnum $state): int|string => $state->value, $states);

        $query->whereIn($this->getStateColumn(), $values);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereNotState(Builder $query, BackedEnum ...$states): void
    {
        $values = array_map(fn (BackedEnum $state): int|string => $state->value, $states);

        $query->whereNotIn($this->getStateColumn(), $values);
    }
}
