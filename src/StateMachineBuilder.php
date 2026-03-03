<?php

declare(strict_types=1);

namespace Maquina;

use BackedEnum;
use Closure;
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
     * @var array<int|string, list<BackedEnum>>
     */
    private array $transitions = [];

    /**
     * @var list<BackedEnum>
     */
    private array $finalStates = [];

    /**
     * @var array<string, list<Closure>>
     */
    private array $guards = [];

    private ?BackedEnum $currentFromState = null;

    /**
     * @var list<BackedEnum>
     */
    private array $lastToStates = [];

    /** @var class-string<BackedEnum>|null */
    private ?string $enumClass = null;

    /**
     * Define the source state for transitions
     */
    public function from(BackedEnum $state): self
    {
        $this->trackEnumClass($state);
        $this->currentFromState = $state;
        $this->lastToStates = [];

        if (! isset($this->transitions[$state->value])) {
            $this->transitions[$state->value] = [];
        }

        return $this;
    }

    /**
     * Define target states for the current source state
     */
    public function to(BackedEnum ...$states): self
    {
        $from = $this->currentFromState;

        if ($from === null) {
            throw new InvalidArgumentException('Must call from() before to()');
        }

        $this->lastToStates = [];

        foreach ($states as $state) {
            $this->trackEnumClass($state);

            if (! in_array($state, $this->transitions[$from->value], true)) {
                $this->transitions[$from->value][] = $state;
            }

            $this->lastToStates[] = $state;
        }

        return $this;
    }

    public function guard(Closure $guard): self
    {
        $from = $this->currentFromState;

        if ($from === null || $this->lastToStates === []) {
            throw new InvalidArgumentException('Must call from()->to() before guard()');
        }

        foreach ($this->lastToStates as $to) {
            $key = $from->value.':'.$to->value;
            $this->guards[$key][] = $guard;
        }

        return $this;
    }

    /**
     * Mark states as final (no outgoing transitions allowed)
     * Optional: If not called, states with no outgoing transitions are auto-detected as final
     */
    public function final(BackedEnum ...$states): self
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
     *
     * @param  class-string<BackedEnum>|null  $enumClass
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

        return new StateMachine($resolvedClass, $this->transitions, $this->finalStates, $this->guards);
    }

    private function trackEnumClass(BackedEnum $state): void
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
