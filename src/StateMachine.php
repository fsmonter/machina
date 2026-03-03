<?php

declare(strict_types=1);

namespace Maquina;

use BackedEnum;

class StateMachine
{
    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @param  array<int|string, list<BackedEnum>>  $transitions
     * @param  list<BackedEnum>  $finalStates
     */
    public function __construct(
        private readonly string $enumClass,
        private readonly array $transitions,
        private readonly array $finalStates = []
    ) {}

    public function canTransition(BackedEnum $from, BackedEnum $target): bool
    {
        return in_array($target, $this->getTransitions($from), true);
    }

    /**
     * @return list<BackedEnum>
     */
    public function getTransitions(BackedEnum $from): array
    {
        return $this->transitions[$from->value] ?? [];
    }

    public function isFinal(BackedEnum $state): bool
    {
        if (! empty($this->finalStates)) {
            return in_array($state, $this->finalStates, true);
        }

        return empty($this->getTransitions($state));
    }

    /**
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
     * @return list<BackedEnum>
     */
    public function getAllStates(): array
    {
        $enumClass = $this->enumClass;
        $states = [];

        foreach (array_keys($this->transitions) as $value) {
            $states[] = $enumClass::from($value);
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
     * @return array{enum_class: class-string<BackedEnum>, transitions: array<int|string, list<int|string>>, final_states: list<int|string>}
     */
    public function toArray(): array
    {
        $export = [];

        foreach ($this->transitions as $sourceValue => $transitions) {
            $export[$sourceValue] = array_map(fn (BackedEnum $state): int|string => $state->value, $transitions);
        }

        return [
            'enum_class' => $this->enumClass,
            'transitions' => $export,
            'final_states' => array_map(fn (BackedEnum $state): int|string => $state->value, $this->finalStates),
        ];
    }

    /**
     * @param  array{enum_class: class-string<BackedEnum>, transitions: array<int|string, list<int|string>>, final_states?: list<int|string>}  $data
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

        return new self($enumClass, $transitions, $finalStates);
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @return \Closure(int|string): (int|string)
     */
    private static function valueCasterFor(string $enumClass): \Closure
    {
        $reflection = new \ReflectionEnum($enumClass);
        $backingType = (string) $reflection->getBackingType();

        return $backingType === 'int'
            ? fn (int|string $value): int => (int) $value
            : fn (int|string $value): string => (string) $value;
    }
}
