<?php

declare(strict_types=1);

namespace Maquina\Concerns;

use BackedEnum;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maquina\Exceptions\InvalidStateTransitionException;
use Maquina\StateMachine;
use Maquina\StateMachineBuilder;

trait HasStateMachine
{
    private ?StateMachine $stateMachine = null;

    abstract protected function defineStateMachine(): StateMachineBuilder;

    protected function getStateColumn(): string
    {
        return 'state';
    }

    protected function getStateEnumClass(): string
    {
        $casts = $this->getCasts();
        $stateAttribute = $this->getStateColumn();

        if (! isset($casts[$stateAttribute])) {
            throw new InvalidArgumentException("State attribute '{$stateAttribute}' must be cast to an enum");
        }

        return $casts[$stateAttribute];
    }

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
     * @param  array<string, mixed>  $additionalData
     */
    public function transitionTo(BackedEnum $newState, array $additionalData = []): bool
    {
        $currentState = $this->getCurrentState();

        if (! $this->getStateMachine()->canTransition($currentState, $newState)) {
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

    public function canTransitionTo(BackedEnum $targetState): bool
    {
        $currentState = $this->getCurrentState();

        return $this->getStateMachine()->canTransition($currentState, $targetState);
    }

    /**
     * @return array<int, BackedEnum>
     */
    public function getAllowedTransitions(): array
    {
        return $this->getStateMachine()->getTransitions($this->getCurrentState());
    }

    public function isInFinalState(): bool
    {
        return $this->getStateMachine()->isFinal($this->getCurrentState());
    }

    protected function getCurrentState(): BackedEnum
    {
        $state = $this->getAttribute($this->getStateColumn());

        if ($state === null) {
            throw new InvalidStateTransitionException(
                "State attribute '{$this->getStateColumn()}' is null"
            );
        }

        return $state;
    }

    protected function fireTransitionEvent(BackedEnum $oldState, BackedEnum $newState): void
    {
        $eventClass = $this->getTransitionEventClass();

        if ($eventClass && class_exists($eventClass)) {
            event(new $eventClass($this, $oldState, $newState));
        }
    }

    protected function getTransitionEventClass(): ?string
    {
        return null;
    }

    protected function afterTransition(BackedEnum $oldState, BackedEnum $newState): void {}
}
