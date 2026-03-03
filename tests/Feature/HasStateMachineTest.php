<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Maquina\Exceptions\InvalidStateTransitionException;
use Workbench\App\Models\TestModel;
use Tests\TestState;

beforeEach(function () {
    $this->model = TestModel::create(['state' => TestState::Pending]);
});

it('transitions to a valid state and persists to DB', function () {
    $this->model->transitionTo(TestState::Processing);

    expect($this->model->fresh()->state)->toBe(TestState::Processing);
});

it('throws on invalid transitions', function () {
    expect(fn () => $this->model->transitionTo(TestState::Completed))
        ->toThrow(InvalidStateTransitionException::class, 'Cannot transition from pending to completed');
});

it('throws when state attribute is null', function () {
    DB::table('test_models')->where('id', $this->model->id)->update(['state' => null]);
    $model = $this->model->fresh();

    expect(fn () => $model->canTransitionTo(TestState::Processing))
        ->toThrow(InvalidStateTransitionException::class, "State attribute 'state' is null");
});

it('throws on concurrent state modification', function () {
    DB::table('test_models')
        ->where('id', $this->model->id)
        ->update(['state' => TestState::Processing->value]);

    expect(fn () => $this->model->transitionTo(TestState::Processing))
        ->toThrow(InvalidStateTransitionException::class, 'state was modified concurrently');
});

it('checks canTransitionTo correctly', function () {
    expect($this->model->canTransitionTo(TestState::Processing))->toBeTrue();
    expect($this->model->canTransitionTo(TestState::Cancelled))->toBeTrue();
    expect($this->model->canTransitionTo(TestState::Completed))->toBeFalse();
    expect($this->model->canTransitionTo(TestState::Failed))->toBeFalse();
});

it('returns allowed transitions from current state', function () {
    $allowed = $this->model->getAllowedTransitions();

    expect($allowed)->toContain(TestState::Processing);
    expect($allowed)->toContain(TestState::Cancelled);
    expect($allowed)->toHaveCount(2);
});

it('detects final states', function () {
    expect($this->model->isInFinalState())->toBeFalse();

    $this->model->transitionTo(TestState::Cancelled);

    expect($this->model->isInFinalState())->toBeTrue();
    expect($this->model->getAllowedTransitions())->toBe([]);
});

it('merges additional data during transition', function () {
    $this->model->transitionTo(TestState::Processing, ['notes' => 'Started processing']);

    $fresh = $this->model->fresh();
    expect($fresh->state)->toBe(TestState::Processing);
    expect($fresh->notes)->toBe('Started processing');
});

it('fires transition event when event class is configured', function () {
    Event::fake();

    $model = new class (['state' => TestState::Pending]) extends TestModel {
        protected function getTransitionEventClass(): ?string
        {
            return \Tests\Stubs\TestTransitionEvent::class;
        }
    };
    $model->save();

    $model->transitionTo(TestState::Processing);

    Event::assertDispatched(\Tests\Stubs\TestTransitionEvent::class, function ($event) use ($model) {
        return $event->model->is($model)
            && $event->oldState === TestState::Pending
            && $event->newState === TestState::Processing;
    });
});

it('does not fire event when event class is null', function () {
    Event::fake();

    $this->model->transitionTo(TestState::Processing);

    Event::assertNothingDispatched();
});

it('calls afterTransition hook', function () {
    $hookCalled = false;

    $model = new class (['state' => TestState::Pending]) extends TestModel {
        public static ?\Closure $hook = null;

        protected function afterTransition(\BackedEnum $oldState, \BackedEnum $newState): void
        {
            if (static::$hook) {
                (static::$hook)($oldState, $newState);
            }
        }
    };
    $model::$hook = function ($old, $new) use (&$hookCalled) {
        $hookCalled = true;
        expect($old)->toBe(TestState::Pending);
        expect($new)->toBe(TestState::Processing);
    };
    $model->save();

    $model->transitionTo(TestState::Processing);

    expect($hookCalled)->toBeTrue();
});

it('transitions through a complete workflow', function () {
    $this->model->transitionTo(TestState::Processing);
    $this->model->transitionTo(TestState::Completed);

    expect($this->model->fresh()->state)->toBe(TestState::Completed);
    expect($this->model->isInFinalState())->toBeTrue();
});

it('exposes the state machine instance', function () {
    $sm = $this->model->getStateMachine();

    expect($sm)->toBeInstanceOf(\Maquina\StateMachine::class);
    expect($sm->canTransition(TestState::Pending, TestState::Processing))->toBeTrue();
});

it('rolls back state on failed transition within transaction', function () {
    DB::table('test_models')
        ->where('id', $this->model->id)
        ->update(['state' => TestState::Processing->value]);

    try {
        $this->model->transitionTo(TestState::Processing);
    } catch (InvalidStateTransitionException) {
    }

    expect($this->model->fresh()->state)->toBe(TestState::Processing);
    expect($this->model->getAttribute('state'))->toBe(TestState::Pending);
});
