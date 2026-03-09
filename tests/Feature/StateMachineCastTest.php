<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Machina\Events\StateTransitioned;
use Machina\Exceptions\InvalidStateTransitionException;
use Machina\State;
use Tests\Stubs\TestCustomEventCast;
use Tests\TestState;
use Workbench\App\Models\TestModel;

beforeEach(function () {
    $this->model = TestModel::create(['state' => TestState::Pending]);
});

it('returns a State value object from the cast', function () {
    expect($this->model->state)->toBeInstanceOf(State::class);
    expect($this->model->state->value())->toBe(TestState::Pending);
});

it('transitions to a valid state and persists to DB', function () {
    $this->model->state->transitionTo(TestState::Processing);

    expect($this->model->fresh()->state->value())->toBe(TestState::Processing);
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
    expect($fresh->state->value())->toBe(TestState::Processing);
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
        protected $casts = [
            'state' => TestCustomEventCast::class,
        ];
    };
    $model->save();

    $model->state->transitionTo(TestState::Processing);

    Event::assertDispatched(\Tests\Stubs\TestTransitionEvent::class);
    Event::assertNotDispatched(StateTransitioned::class);
});

it('transitions through a complete workflow', function () {
    $this->model->state->transitionTo(TestState::Processing);
    $this->model->state->transitionTo(TestState::Completed);

    expect($this->model->fresh()->state->value())->toBe(TestState::Completed);
    expect($this->model->state->isFinal())->toBeTrue();
});

it('exposes the state machine instance', function () {
    $sm = $this->model->state->stateMachine();

    expect($sm)->toBeInstanceOf(\Machina\StateMachine::class);
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

    expect($this->model->fresh()->state->value())->toBe(TestState::Processing);
    expect($this->model->state->value())->toBe(TestState::Pending);
});

it('compares state with is()', function () {
    expect($this->model->state->is(TestState::Pending))->toBeTrue();
    expect($this->model->state->is(TestState::Processing))->toBeFalse();
});

it('converts to string', function () {
    expect((string) $this->model->state)->toBe('pending');
});
