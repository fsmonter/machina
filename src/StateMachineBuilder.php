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

    /**
     * Define the source state for transitions
     */
    public function from(\BackedEnum $state): self
    {
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
            if (! in_array($state, $this->finalStates, true)) {
                $this->finalStates[] = $state;
            }

            // Ensure final states have empty transition arrays
            $this->transitions[$state->value] = [];
        }

        return $this;
    }

    /**
     * Build the final StateMachine instance
     */
    public function build(string $enumClass): StateMachine
    {
        // Convert int keys to string keys for consistent typing
        $normalizedTransitions = [];
        foreach ($this->transitions as $key => $value) {
            $normalizedTransitions[(string) $key] = $value;
        }

        return new StateMachine($enumClass, $normalizedTransitions, $this->finalStates);
    }
}
