<?php

declare(strict_types=1);

namespace Maquina;

use InvalidArgumentException;

/**
 * Fluent builder for creating state machine definitions
 *
 * Usage:
 * machine()
 *     ->from(MyEnum::Pending)->to(MyEnum::Processing, MyEnum::Complete)
 *     ->from(MyEnum::Processing)->to(MyEnum::Complete, MyEnum::Failed)
 *     ->final(MyEnum::Complete, MyEnum::Failed)
 *     ->build(MyEnum::class);
 */
class StateMachineBuilder
{
    /**
     * @var array<int|string, array<int, \BackedEnum>>
     */
    private array $transitions = [];

    /**
     * @var array<int, \BackedEnum>
     */
    private array $finalStates = [];

    private ?\BackedEnum $currentFromState = null;

    private ?string $enumClass = null;

    /**
     * Define the source state for transitions
     */
    public function from(\BackedEnum $state): self
    {
        $this->trackEnumClass($state);
        $this->currentFromState = $state;

        if (! isset($this->transitions[$state->value])) {
            $this->transitions[$state->value] = [];
        }

        return $this;
    }

    /**
     * Define target states for the current source state
     */
    public function to(\BackedEnum ...$states): self
    {
        if ($this->currentFromState === null) {
            throw new InvalidArgumentException('Must call from() before to()');
        }

        foreach ($states as $state) {
            $this->trackEnumClass($state);

            if (! in_array($state, $this->transitions[$this->currentFromState->value], true)) {
                $this->transitions[$this->currentFromState->value][] = $state;
            }
        }

        return $this;
    }

    /**
     * Mark states as final (no outgoing transitions allowed)
     * Optional: If not called, states with no outgoing transitions are auto-detected as final
     */
    public function final(\BackedEnum ...$states): self
    {
        foreach ($states as $state) {
            $this->trackEnumClass($state);

            if (! in_array($state, $this->finalStates, true)) {
                $this->finalStates[] = $state;
            }

            $this->transitions[$state->value] = [];
        }

        return $this;
    }

    /**
     * Build the final StateMachine instance
     */
    public function build(?string $enumClass = null): StateMachine
    {
        $resolvedClass = $enumClass ?? $this->enumClass;

        if ($resolvedClass === null) {
            throw new InvalidArgumentException('Enum class must be provided via build() or inferred from states');
        }

        if ($enumClass !== null && $this->enumClass !== null && $enumClass !== $this->enumClass) {
            throw new InvalidArgumentException(
                "Enum class mismatch: build() received {$enumClass} but states use {$this->enumClass}"
            );
        }

        return new StateMachine($resolvedClass, $this->transitions, $this->finalStates);
    }

    private function trackEnumClass(\BackedEnum $state): void
    {
        $class = $state::class;

        if ($this->enumClass === null) {
            $this->enumClass = $class;

            return;
        }

        if ($this->enumClass !== $class) {
            throw new InvalidArgumentException(
                "All states must be the same enum type. Expected {$this->enumClass}, got {$class}"
            );
        }
    }
}
