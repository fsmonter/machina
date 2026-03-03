<?php

declare(strict_types=1);

namespace Maquina\Concerns;

use BackedEnum;
use InvalidArgumentException;
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
     */
    protected function getStateEnumClass(): string
    {
        $casts = $this->getCasts();
        $stateAttribute = $this->getStateColumn();

        if (! isset($casts[$stateAttribute])) {
            throw new InvalidArgumentException("State attribute '{$stateAttribute}' must be cast to an enum");
        }

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
        /** @var BackedEnum $currentState */
        $currentState = $this->getAttribute($this->getStateColumn());

        if (! $this->canTransitionTo($newState)) {
            throw new InvalidStateTransitionException(
                "Cannot transition from {$currentState->value} to {$newState->value}"
            );
        }

        $oldState = $currentState;

        $updateData = array_merge($additionalData, [
            $this->getStateColumn() => $newState,
        ]);

        $this->update($updateData);

        $this->fireTransitionEvent($oldState, $newState);

        $this->afterTransition($oldState, $newState);

        return true;
    }

    /**
     * Check if transition to target state is allowed
     */
    public function canTransitionTo(BackedEnum $targetState): bool
    {
        /** @var BackedEnum $currentState */
        $currentState = $this->getAttribute($this->getStateColumn());

        return $this->getStateMachine()->canTransition($currentState, $targetState);
    }

    /**
     * Get all allowed transitions from current state
     *
     * @return array<int, BackedEnum>
     */
    public function getAllowedTransitions(): array
    {
        /** @var BackedEnum $currentState */
        $currentState = $this->getAttribute($this->getStateColumn());

        return $this->getStateMachine()->getTransitions($currentState);
    }

    /**
     * Check if current state is final (no outgoing transitions)
     */
    public function isInFinalState(): bool
    {
        /** @var BackedEnum $currentState */
        $currentState = $this->getAttribute($this->getStateColumn());

        return $this->getStateMachine()->isFinal($currentState);
    }

    /**
     * Fire the appropriate transition event
     */
    protected function fireTransitionEvent(BackedEnum $oldState, BackedEnum $newState): void
    {
        $eventClass = $this->getTransitionEventClass();

        if ($eventClass && class_exists($eventClass)) {
            event(new $eventClass($this, $oldState, $newState));
        }
    }

    /**
     * Get the event class name for transitions
     * Override in model to fire events on state transitions
     */
    protected function getTransitionEventClass(): ?string
    {
        return null;
    }

    /**
     * Hook called after a successful state transition
     * Override in models to implement custom post-transition logic
     */
    protected function afterTransition(BackedEnum $oldState, BackedEnum $newState): void {}
}
