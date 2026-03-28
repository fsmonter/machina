<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use Closure;

/**
 * Compiled state machine for efficient transition lookups
 */
class StateMachine
{
    /** @var array<int|string, true> */
    private readonly array $finalStatesIndex;

    /** @var array<int|string, array<string, Operation>> */
    private readonly array $operationsByName;

    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @param  array<int|string, list<BackedEnum>>  $transitions
     * @param  list<BackedEnum>  $finalStates
     * @param  array<int|string, array<int|string, list<Closure>>>  $guards
     * @param  array<int|string, list<Operation>>  $operations
     */
    public function __construct(
        private readonly string $enumClass,
        private readonly array $transitions,
        private readonly array $finalStates = [],
        private readonly array $guards = [],
        private readonly array $operations = [],
        private readonly ?BackedEnum $initialState = null,
    ) {
        $index = [];
        foreach ($this->finalStates as $state) {
            $index[$state->value] = true;
        }
        $this->finalStatesIndex = $index;

        $byName = [];
        foreach ($this->operations as $fromValue => $ops) {
            foreach ($ops as $op) {
                $byName[$fromValue][$op->name] = $op;
            }
        }
        $this->operationsByName = $byName;
    }

    /**
     * @return class-string<BackedEnum>
     */
    public function enumClass(): string
    {
        return $this->enumClass;
    }

    public function initialState(): ?BackedEnum
    {
        return $this->initialState;
    }

    public function canTransition(BackedEnum $from, BackedEnum $target, ?object $context = null): bool
    {
        if (! in_array($target, $this->getTransitions($from), true)) {
            return false;
        }

        return $this->evaluateGuards($from, $target, $context);
    }

    private function evaluateGuards(BackedEnum $from, BackedEnum $target, ?object $context = null): bool
    {
        $guards = $this->guards[$from->value][$target->value] ?? [];

        foreach ($guards as $guard) {
            if (! $guard($context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all allowed transitions from the given state
     *
     * @return list<BackedEnum>
     */
    public function getTransitions(BackedEnum $from): array
    {
        return $this->transitions[$from->value] ?? [];
    }

    /**
     * Check if the given state is final (has no outgoing transitions)
     */
    public function isFinal(BackedEnum $state): bool
    {
        return isset($this->finalStatesIndex[$state->value])
            || empty($this->getTransitions($state));
    }

    /**
     * Get all states that can transition to the target state
     *
     * @return list<BackedEnum>
     */
    public function getSourceStates(BackedEnum $target): array
    {
        $enumClass = $this->enumClass;
        $sources = [];

        foreach ($this->transitions as $sourceValue => $transitions) {
            if (in_array($target, $transitions, true)) {
                $sources[] = $enumClass::from($sourceValue);
            }
        }

        return $sources;
    }

    /**
     * Get all states defined in this state machine
     *
     * @return list<BackedEnum>
     */
    public function getAllStates(): array
    {
        $enumClass = $this->enumClass;
        /** @var array<int|string, BackedEnum> $seen */
        $seen = [];

        foreach (array_keys($this->transitions) as $value) {
            $seen[$value] = $enumClass::from($value);
        }

        foreach ($this->transitions as $transitions) {
            foreach ($transitions as $state) {
                $seen[$state->value] ??= $state;
            }
        }

        return array_values($seen);
    }

    public function findOperation(BackedEnum $from, string $name): ?Operation
    {
        return $this->operationsByName[$from->value][$name] ?? null;
    }

    public function canSend(BackedEnum $from, string $name, ?object $context = null): bool
    {
        $operation = $this->findOperation($from, $name);

        if ($operation === null) {
            return false;
        }

        if ($operation->to !== null && ! $this->canTransition($from, $operation->to, $context)) {
            return false;
        }

        foreach ($operation->guards as $guard) {
            if (! $guard($context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<Operation>
     */
    public function getOperations(BackedEnum $from): array
    {
        return $this->operations[$from->value] ?? [];
    }

    /**
     * Converts the state machine definition as an array (useful for caching)
     *
     * @return array{enum_class: class-string<BackedEnum>, transitions: array<int|string, list<int|string>>, final_states: list<int|string>}
     */
    public function toArray(): array
    {
        /** @var array<int|string, list<int|string>> $export */
        $export = [];

        foreach ($this->transitions as $sourceValue => $transitions) {
            $export[$sourceValue] = array_map(fn (BackedEnum $state): int|string => $state->value, $transitions);
        }

        /** @var list<int|string> $finalStates */
        $finalStates = array_map(fn (BackedEnum $state): int|string => $state->value, $this->finalStates);

        $data = [
            'enum_class' => $this->enumClass,
            'transitions' => $export,
            'final_states' => $finalStates,
        ];

        if ($this->initialState !== null) {
            $data['initial_state'] = $this->initialState->value;
        }

        return $data;
    }

    /**
     * Create a StateMachine from exported array data
     *
     * @param  array{enum_class: class-string<BackedEnum>, transitions: array<int|string, list<int|string>>, final_states?: list<int|string>, initial_state?: int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        $enumClass = $data['enum_class'];
        $castValue = self::valueCasterFor($enumClass);
        /** @var array<int|string, list<BackedEnum>> $transitions */
        $transitions = [];

        foreach ($data['transitions'] as $sourceValue => $targetValues) {
            $transitions[$sourceValue] = array_map(
                fn (int|string $value): BackedEnum => $enumClass::from($castValue($value)),
                $targetValues
            );
        }

        /** @var list<BackedEnum> $finalStates */
        $finalStates = [];
        if (isset($data['final_states'])) {
            $finalStates = array_map(
                fn (int|string $value): BackedEnum => $enumClass::from($castValue($value)),
                $data['final_states']
            );
        }

        /** @var int|string|null $rawInitial */
        $rawInitial = $data['initial_state'] ?? null;
        $initialState = $rawInitial !== null
            ? $enumClass::from($castValue($rawInitial))
            : null;

        return new self($enumClass, $transitions, $finalStates, initialState: $initialState);
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @return Closure(int|string): (int|string)
     */
    private static function valueCasterFor(string $enumClass): Closure
    {
        $reflection = new \ReflectionEnum($enumClass);
        $backingType = (string) $reflection->getBackingType();

        return $backingType === 'int'
            ? fn (int|string $value): int => (int) $value
            : fn (int|string $value): string => (string) $value;
    }
}
