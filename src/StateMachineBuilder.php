<?php

declare(strict_types=1);

namespace Maquina;

use BackedEnum;
use InvalidArgumentException;

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

    private ?BackedEnum $currentFromState = null;

    /** @var class-string<BackedEnum>|null */
    private ?string $enumClass = null;

    public function from(BackedEnum $state): self
    {
        $this->trackEnumClass($state);
        $this->currentFromState = $state;

        if (! isset($this->transitions[$state->value])) {
            $this->transitions[$state->value] = [];
        }

        return $this;
    }

    public function to(BackedEnum ...$states): self
    {
        $from = $this->currentFromState;

        if ($from === null) {
            throw new InvalidArgumentException('Must call from() before to()');
        }

        foreach ($states as $state) {
            $this->trackEnumClass($state);

            if (! in_array($state, $this->transitions[$from->value], true)) {
                $this->transitions[$from->value][] = $state;
            }
        }

        return $this;
    }

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

        return new StateMachine($resolvedClass, $this->transitions, $this->finalStates);
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
