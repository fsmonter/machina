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
 *     ->initial(MyEnum::Pending)
 *     ->state(MyEnum::Pending, function ($state) {
 *         $state->on('process')->target(MyEnum::Processing)
 *             ->guard(fn ($m) => $m->total > 0);
 *         $state->on('cancel')->target(MyEnum::Cancelled);
 *     })
 *     ->transition(from: MyEnum::Processing, to: MyEnum::Complete)
 *     ->final(MyEnum::Complete, MyEnum::Cancelled)
 *     ->build();
 */
class StateMachineBuilder
{
    /** @var array<int|string, list<BackedEnum>> */
    private array $transitions = [];

    /** @var list<BackedEnum> */
    private array $finalStates = [];

    /** @var array<int|string, array<int|string, list<Closure>>> */
    private array $guards = [];

    /** @var list<array{name: string, from: BackedEnum, to: ?BackedEnum, guards: list<Closure>, action: ?Closure}> */
    private array $operationDefs = [];

    /** @var class-string<BackedEnum>|null */
    private ?string $enumClass = null;

    private ?BackedEnum $initialState = null;

    /**
     * Define the initial state for the state machine
     */
    public function initial(BackedEnum $state): self
    {
        $this->trackEnumClass($state);
        $this->initialState = $state;

        return $this;
    }

    /**
     * Define operations available from a state
     *
     * @param  Closure(StateBuilder): void  $callback
     */
    public function state(BackedEnum $state, Closure $callback): self
    {
        $this->trackEnumClass($state);

        if (in_array($state, $this->finalStates, true)) {
            throw new InvalidArgumentException(
                "Cannot define operations from final state {$state->value}"
            );
        }

        if (! isset($this->transitions[$state->value])) {
            $this->transitions[$state->value] = [];
        }

        $stateBuilder = new StateBuilder($state);
        $callback($stateBuilder);

        foreach ($stateBuilder->getOperations() as $def) {
            $target = $def['target'];

            if ($target !== null) {
                $this->registerTransition($state, $target);
            }

            $this->operationDefs[] = [
                'name' => $def['name'],
                'from' => $state,
                'to' => $target,
                'guards' => $def['guards'],
                'action' => $def['action'],
            ];
        }

        return $this;
    }

    /**
     * Define a direct transition between states
     *
     * @param  Closure|list<Closure>|null  $guard
     */
    public function transition(
        BackedEnum $from,
        BackedEnum $to,
        Closure|array|null $guard = null,
    ): self {
        $this->registerTransition($from, $to);

        foreach ($this->normalizeGuards($guard) as $g) {
            $this->guards[$from->value][$to->value][] = $g;
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

            if (! empty($this->transitions[$state->value] ?? [])) {
                throw new InvalidArgumentException(
                    "Cannot mark state {$state->value} as final after defining transitions from it"
                );
            }

            foreach ($this->operationDefs as $operation) {
                if ($operation['from']->value === $state->value) {
                    throw new InvalidArgumentException(
                        "Cannot mark state {$state->value} as final after defining operations from it"
                    );
                }
            }

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
                action: $def['action'],
            );
        }

        return new StateMachine($resolvedClass, $this->transitions, $this->finalStates, $this->guards, $operations, $this->initialState);
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

    private function registerTransition(BackedEnum $from, ?BackedEnum $to): void
    {
        $this->trackEnumClass($from);

        if ($to !== null) {
            $this->trackEnumClass($to);
        }

        if (in_array($from, $this->finalStates, true)) {
            throw new InvalidArgumentException(
                "Cannot define transitions from final state {$from->value}"
            );
        }

        if (! isset($this->transitions[$from->value])) {
            $this->transitions[$from->value] = [];
        }

        if ($to !== null && ! in_array($to, $this->transitions[$from->value], true)) {
            $this->transitions[$from->value][] = $to;
        }
    }

    /**
     * @param  Closure|list<Closure>|null  $guard
     * @return list<Closure>
     */
    private function normalizeGuards(Closure|array|null $guard): array
    {
        return match (true) {
            $guard instanceof Closure => [$guard],
            is_array($guard) => $guard,
            default => [],
        };
    }
}
