<?php

declare(strict_types=1);

namespace Machina;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, class-string<Machina>> $stateMachines
 */
trait HasStateMachine
{
    public function initializeHasStateMachine(): void
    {
        $casts = [];
        foreach ($this->stateMachines as $column => $machinaClass) {
            $casts[$column] = MachinaCast::class.':'.$machinaClass;
        }

        $this->mergeCasts($casts);
    }

    public static function bootHasStateMachine(): void
    {
        static::creating(function (Model $model) {
            if (! property_exists($model, 'stateMachines')) {
                return;
            }

            /** @var array<string, class-string<Machina>> $stateMachines */
            $stateMachines = $model->stateMachines;

            foreach ($stateMachines as $column => $machinaClass) {
                if ($model->getAttribute($column) !== null) {
                    continue;
                }

                /** @var Machina $machina */
                $machina = new $machinaClass;
                $initialState = $machina->stateMachine()->initialState();

                if ($initialState !== null) {
                    $model->setAttribute($column, $initialState);
                }
            }
        });
    }
}
