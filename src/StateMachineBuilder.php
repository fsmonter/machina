<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use Closure;
use InvalidArgumentException;

/**
 * Fluent builder for creating state machine definitions
 *
 * Usage:
 * machina()
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
     * @var array<int|string, array<int|string, list<Closure>>>
     */
    private array $guards = [];

    /**
     * @var list<array{name: string, from: BackedEnum, to: ?BackedEnum, guards: list<Closure>, do: ?Closure}>
     */
    private array $operationDefs = [];

    private ?int $currentOperationIndex = null;

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

        if (in_array($state, $this->finalStates, true)) {
            throw new InvalidArgumentException(
                "Cannot define outgoing transitions from final state {$state->value}"
            );
        }

        $this->currentFromState = $state;
        $this->currentOperationIndex = null;
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

        if ($this->currentOperationIndex !== null) {
            if (count($states) !== 1) {
                throw new InvalidArgumentException('Operations accept exactly one target state');
            }

            $this->operationDefs[$this->currentOperationIndex]['to'] = $states[0];

            $this->trackEnumClass($states[0]);

            if (! in_array($states[0], $this->transitions[$from->value], true)) {
                $this->transitions[$from->value][] = $states[0];
            }

            $this->lastToStates = [$states[0]];

            return $this;
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

        if ($this->currentOperationIndex !== null) {
            if ($from === null) {
                throw new InvalidArgumentException('Must call from() before guard()');
            }

            $this->operationDefs[$this->currentOperationIndex]['guards'][] = $guard;

            return $this;
        }

        if ($from === null || $this->lastToStates === []) {
            throw new InvalidArgumentException('Must call from()->to() before guard()');
        }

        foreach ($this->lastToStates as $to) {
            $this->guards[$from->value][$to->value][] = $guard;
        }

        return $this;
    }

    /**
     * Define a named operation under the current from() state
     */
    public function on(string $name): self
    {
        if ($this->currentFromState === null) {
            throw new InvalidArgumentException('Must call from() before on()');
        }

        $this->lastToStates = [];

        $this->operationDefs[] = [
            'name' => $name,
            'from' => $this->currentFromState,
            'to' => null,
            'guards' => [],
            'do' => null,
        ];
        $this->currentOperationIndex = array_key_last($this->operationDefs);

        return $this;
    }

    /**
     * Attach a side-effect closure to the current operation
     */
    public function do(Closure $action): self
    {
        if ($this->currentOperationIndex === null) {
            throw new InvalidArgumentException('Must call on() before do()');
        }

        $this->operationDefs[$this->currentOperationIndex]['do'] = $action;

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

        $operations = [];
        foreach ($this->operationDefs as $def) {
            $operations[$def['from']->value][] = new Operation(
                name: $def['name'],
                from: $def['from'],
                to: $def['to'],
                guards: $def['guards'],
                do: $def['do'],
            );
        }

        return new StateMachine($resolvedClass, $this->transitions, $this->finalStates, $this->guards, $operations);
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
