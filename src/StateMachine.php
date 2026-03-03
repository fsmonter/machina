<?php

declare(strict_types=1);

namespace Maquina;

/**
 * Compiled state machine for efficient transition lookups
 */
class StateMachine
{
    /**
     * @param  array<string, array<int, \BackedEnum>>  $transitions
     * @param  array<int, \BackedEnum>  $finalStates
     */
    public function __construct(
        private readonly string $enumClass,
        private readonly array $transitions,
        private readonly array $finalStates = []
    ) {}

    /**
     * Check if a transition from source to target state is valid
     */
    public function canTransition(\BackedEnum $from, \BackedEnum $target): bool
    {
        $allowedTransitions = $this->getTransitions($from);

        return in_array($target, $allowedTransitions, true);
    }

    /**
     * Get all allowed transitions from the given state
     *
     * @return array<int, \BackedEnum>
     */
    public function getTransitions(\BackedEnum $from): array
    {
        return $this->transitions[$from->value] ?? [];
    }

    /**
     * Check if the given state is final (has no outgoing transitions)
     */
    public function isFinal(\BackedEnum $state): bool
    {
        if (! empty($this->finalStates)) {
            return in_array($state, $this->finalStates, true);
        }

        return empty($this->getTransitions($state));
    }

    /**
     * Get all states that can transition to the target state
     *
     * @return array<int, \BackedEnum>
     */
    public function getSourceStates(\BackedEnum $target): array
    {
        $sources = [];

        foreach ($this->transitions as $sourceValue => $transitions) {
            if (in_array($target, $transitions, true)) {
                $sourceEnum = $this->enumClass::from($sourceValue);
                $sources[] = $sourceEnum;
            }
        }

        return $sources;
    }

    /**
     * Get all states defined in this state machine
     *
     * @return array<int, \BackedEnum>
     */
    public function getAllStates(): array
    {
        $states = [];

        foreach (array_keys($this->transitions) as $value) {
            $states[] = $this->enumClass::from($value);
        }

        foreach ($this->transitions as $transitions) {
            foreach ($transitions as $state) {
                if (! in_array($state, $states, true)) {
                    $states[] = $state;
                }
            }
        }

        return $states;
    }

    /**
     * Converts the state machine definition as an array (useful for caching)
     *
     * @return array{enum_class: string, transitions: array<int|string, array<int, int|string>>, final_states: array<int, int|string>}
     */
    public function toArray(): array
    {
        $export = [];

        foreach ($this->transitions as $sourceValue => $transitions) {
            $export[$sourceValue] = array_map(fn ($state) => $state->value, $transitions);
        }

        return [
            'enum_class' => $this->enumClass,
            'transitions' => $export,
            'final_states' => array_map(fn ($state) => $state->value, $this->finalStates),
        ];
    }

    /**
     * Create a StateMachine from exported array data
     *
     * @param  array{enum_class: string, transitions: array<int|string, array<int, int|string>>, final_states?: array<int, int|string>}  $data
     */
    public static function fromArray(array $data): self
    {
        $enumClass = $data['enum_class'];
        $castValue = self::valueCasterFor($enumClass);
        $transitions = [];

        foreach ($data['transitions'] as $sourceValue => $targetValues) {
            $transitions[$sourceValue] = array_map(
                fn ($value) => $enumClass::from($castValue($value)),
                $targetValues
            );
        }

        $finalStates = [];
        if (isset($data['final_states'])) {
            $finalStates = array_map(
                fn ($value) => $enumClass::from($castValue($value)),
                $data['final_states']
            );
        }

        return new self($enumClass, $transitions, $finalStates);
    }

    /**
     * @return \Closure(mixed): (int|string)
     */
    private static function valueCasterFor(string $enumClass): \Closure
    {
        $reflection = new \ReflectionEnum($enumClass);
        $backingType = (string) $reflection->getBackingType();

        return $backingType === 'int'
            ? fn ($value) => (int) $value
            : fn ($value) => (string) $value;
    }
}
