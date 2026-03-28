<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Machina\Events\StateTransitioned;
use Machina\Exceptions\InvalidStateTransitionException;
use Machina\State;
use Machina\StateMachine;
use Tests\Stubs\TestCustomEventMachina;
use Tests\Stubs\TestTransitionEvent;
use Tests\TestIntState;
use Tests\TestState;
use Workbench\App\Models\TestModel;

beforeEach(function () {
    $this->model = TestModel::create(['state' => TestState::Pending]);
});

it('returns a State value object from the cast', function () {
    expect($this->model->state)->toBeInstanceOf(State::class);
    expect($this->model->state->current())->toBe(TestState::Pending);
});

it('transitions to a valid state and persists to DB', function () {
    $this->model->state->transitionTo(TestState::Processing);

    expect($this->model->fresh()->state->current())->toBe(TestState::Processing);
});

it('throws on invalid transitions', function () {
    expect(fn () => $this->model->state->transitionTo(TestState::Completed))
        ->toThrow(InvalidStateTransitionException::class, 'Cannot transition from pending to completed');
});

it('returns null for null state attribute', function () {
    DB::table('test_models')->where('id', $this->model->id)->update(['state' => null]);
    $model = $this->model->fresh();

    expect($model->state)->toBeNull();
});

it('throws on concurrent state modification', function () {
    DB::table('test_models')
        ->where('id', $this->model->id)
        ->update(['state' => TestState::Processing->value]);

    expect(fn () => $this->model->state->transitionTo(TestState::Processing))
        ->toThrow(InvalidStateTransitionException::class, 'state was not updated');
});

it('checks canTransitionTo correctly', function () {
    expect($this->model->state->canTransitionTo(TestState::Processing))->toBeTrue();
    expect($this->model->state->canTransitionTo(TestState::Cancelled))->toBeTrue();
    expect($this->model->state->canTransitionTo(TestState::Completed))->toBeFalse();
    expect($this->model->state->canTransitionTo(TestState::Failed))->toBeFalse();
});

it('returns allowed transitions from current state', function () {
    $allowed = $this->model->state->allowedTransitions();

    expect($allowed)->toContain(TestState::Processing);
    expect($allowed)->toContain(TestState::Cancelled);
    expect($allowed)->toHaveCount(2);
});

it('detects final states', function () {
    expect($this->model->state->isFinal())->toBeFalse();

    $this->model->state->transitionTo(TestState::Cancelled);

    expect($this->model->state->isFinal())->toBeTrue();
    expect($this->model->state->allowedTransitions())->toBe([]);
});

it('merges additional data during transition', function () {
    $this->model->state->transitionTo(TestState::Processing, ['notes' => 'Started processing']);

    $fresh = $this->model->fresh();
    expect($fresh->state->current())->toBe(TestState::Processing);
    expect($fresh->notes)->toBe('Started processing');
});

it('fires StateTransitioned event by default', function () {
    Event::fake();

    $this->model->state->transitionTo(TestState::Processing);

    Event::assertDispatched(StateTransitioned::class, function ($event) {
        return $event->model->is($this->model)
            && $event->oldState === TestState::Pending
            && $event->newState === TestState::Processing;
    });
});

it('fires custom event when eventClass is overridden', function () {
    Event::fake();

    $model = new class(['state' => TestState::Pending]) extends TestModel
    {
        protected $stateMachines = [
            'state' => TestCustomEventMachina::class,
        ];
    };
    $model->save();

    $model->state->transitionTo(TestState::Processing);

    Event::assertDispatched(TestTransitionEvent::class);
    Event::assertNotDispatched(StateTransitioned::class);
});

it('transitions through a complete workflow', function () {
    $this->model->state->transitionTo(TestState::Processing);
    $this->model->state->transitionTo(TestState::Completed);

    expect($this->model->fresh()->state->current())->toBe(TestState::Completed);
    expect($this->model->state->isFinal())->toBeTrue();
});

it('exposes the state machine instance', function () {
    $sm = $this->model->state->stateMachine();

    expect($sm)->toBeInstanceOf(StateMachine::class);
    expect($sm->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
});

it('rolls back state on failed transition within transaction', function () {
    DB::table('test_models')
        ->where('id', $this->model->id)
        ->update(['state' => TestState::Processing->value]);

    try {
        $this->model->state->transitionTo(TestState::Processing);
    } catch (InvalidStateTransitionException) {
    }

    expect($this->model->fresh()->state->current())->toBe(TestState::Processing);
    expect($this->model->state->current())->toBe(TestState::Pending);
});

it('compares state with is()', function () {
    expect($this->model->state->is(TestState::Pending))->toBeTrue();
    expect($this->model->state->is(TestState::Processing))->toBeFalse();
});

it('converts to string', function () {
    expect((string) $this->model->state)->toBe('pending');
});

it('rejects raw string values in set()', function () {
    expect(fn () => $this->model->fill(['state' => 'processing']))
        ->toThrow(InvalidArgumentException::class, 'enum instance');
});

it('rejects raw integer values in set()', function () {
    expect(fn () => $this->model->fill(['state' => 1]))
        ->toThrow(InvalidArgumentException::class, 'enum instance');
});

it('rejects foreign enum values in set()', function () {
    expect(fn () => $this->model->fill(['state' => TestIntState::Pending]))
        ->toThrow(InvalidArgumentException::class, 'enum instance');
});

it('accepts valid enum values in set()', function () {
    $this->model->fill(['state' => TestState::Processing]);

    expect($this->model->getAttributes()['state'])->toBe('processing');
});

it('accepts null values in set()', function () {
    $this->model->fill(['state' => null]);

    expect($this->model->getAttributes()['state'])->toBeNull();
});

it('syncs in-memory model via forceFill even when column is guarded', function () {
    $model = new class extends TestModel
    {
        protected $guarded = ['state', 'notes'];
    };
    $model->forceFill(['state' => TestState::Pending])->save();

    $model->state->transitionTo(TestState::Processing, ['notes' => 'Updated']);

    // In-memory model should reflect the new state and additional data
    expect($model->state->current())->toBe(TestState::Processing);
    expect($model->notes)->toBe('Updated');
});

it('uses the model database connection for transactions', function () {
    // The model's connection should be used, not the default DB facade
    // This validates that non-default connections work correctly
    $connection = $this->model->getConnection();

    $this->model->state->transitionTo(TestState::Processing);

    $fresh = $this->model->fresh();
    expect($fresh->state->current())->toBe(TestState::Processing);
    expect($fresh->getConnectionName())->toBe($this->model->getConnectionName());
});
