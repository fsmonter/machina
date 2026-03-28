<?php

declare(strict_types=1);

namespace Machina;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal cast adapter. Not intended for direct use.
 * Use the HasStateMachine trait with $stateMachines instead.
 *
 * @implements CastsAttributes<State, mixed>
 */
class MachinaCast implements CastsAttributes
{
    public bool $withoutObjectCaching = true;

    private ?Machina $machina = null;

    /**
     * @param  class-string<Machina>  $machinaClass
     */
    public function __construct(
        private readonly string $machinaClass,
    ) {}

    public function machina(): Machina
    {
        return $this->machina ??= new ($this->machinaClass);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?State
    {
        if ($value === null) {
            return null;
        }

        $machina = $this->machina();
        $enumClass = $machina->getEnumClass();

        /** @var int|string $value */
        $enum = $enumClass::from($value);

        return new State($enum, $model, $key, $machina);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|int|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof State) {
            $value = $value->value();
        }

        $enumClass = $this->machina()->getEnumClass();

        if (! $value instanceof BackedEnum) {
            throw new \InvalidArgumentException(
                "Value must be a {$enumClass} enum instance, got ".get_debug_type($value)
            );
        }

        if (! $value instanceof $enumClass) {
            throw new \InvalidArgumentException(
                "Value must be a {$enumClass} enum instance, got ".$value::class
            );
        }

        return $value->value;
    }
}
