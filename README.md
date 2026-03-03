# Maquina

A fluent state machine library for Laravel Eloquent models.

## Installation

```bash
composer require fsmonter/maquina
```

The service provider is auto-discovered. No manual registration needed.

## Quick Start

### 1. Define your states as a backed enum

```php
enum OrderState: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
```

### 2. Add the trait to your model

```php
use Maquina\Concerns\HasStateMachine;
use Maquina\StateMachineBuilder;

class Order extends Model
{
    use HasStateMachine;

    protected $casts = [
        'state' => OrderState::class,
    ];

    protected function defineStateMachine(): StateMachineBuilder
    {
        return machine()
            ->from(OrderState::Pending)->to(OrderState::Processing, OrderState::Cancelled)
            ->from(OrderState::Processing)->to(OrderState::Completed, OrderState::Failed)
            ->final(OrderState::Completed, OrderState::Failed, OrderState::Cancelled);
    }
}
```

### 3. Use it

```php
$order = Order::create(['state' => OrderState::Pending]);

$order->transitionTo(OrderState::Processing);
$order->transitionTo(OrderState::Completed);

$order->canTransitionTo(OrderState::Failed); // false (already completed)
$order->isInFinalState(); // true
```

## Builder API

The `machine()` helper returns a fluent `StateMachineBuilder`:

```php
machine()
    ->from(State::Draft)->to(State::Review)
    ->from(State::Review)->to(State::Published, State::Draft)
    ->final(State::Published)
    ->build();
```

**`from(BackedEnum $state)`** sets the source state for transitions.

**`to(BackedEnum ...$states)`** defines which states the source can transition to.

**`final(BackedEnum ...$states)`** marks states as terminal (optional; states with no outgoing transitions are auto-detected).

**`build(?string $enumClass = null)`** compiles the state machine. The enum class is inferred from the states used, or can be passed explicitly.

## Transition Guards

Guards add conditions that must pass for a transition to be allowed:

```php
protected function defineStateMachine(): StateMachineBuilder
{
    return machine()
        ->from(OrderState::Pending)->to(OrderState::Processing)
            ->guard(fn (Order $model) => $model->total > 0)
            ->guard(fn (Order $model) => $model->items()->exists())
        ->from(OrderState::Processing)->to(OrderState::Completed, OrderState::Failed)
        ->final(OrderState::Completed, OrderState::Failed);
}
```

Guards receive the model instance. All guards on a transition must pass. When a guard fails, `canTransitionTo()` returns false and `transitionTo()` throws `InvalidStateTransitionException`.

Guards also affect `getAllowedTransitions()`, which only returns transitions whose guards pass.

## Query Scopes

Filter models by state:

```php
// Models in specific states
Order::whereState(OrderState::Pending)->get();
Order::whereState(OrderState::Processing, OrderState::Pending)->get();

// Models NOT in specific states
Order::whereNotState(OrderState::Cancelled, OrderState::Failed)->get();
```

## Events

A `StateTransitioned` event fires after every successful transition:

```php
use Maquina\Events\StateTransitioned;

class HandleOrderTransition
{
    public function handle(StateTransitioned $event): void
    {
        $event->model;    // The Eloquent model
        $event->oldState; // Previous state (BackedEnum)
        $event->newState; // New state (BackedEnum)
    }
}
```

To use a custom event class, override `getTransitionEventClass()` in your model:

```php
protected function getTransitionEventClass(): string
{
    return OrderStateChanged::class;
}
```

## Hooks

Override `afterTransition()` for model-level post-transition logic:

```php
protected function afterTransition(BackedEnum $oldState, BackedEnum $newState): void
{
    if ($newState === OrderState::Completed) {
        $this->notify(new OrderCompletedNotification);
    }
}
```

## Additional Data

Pass extra data to update alongside the state:

```php
$order->transitionTo(OrderState::Processing, [
    'processed_at' => now(),
    'processor_id' => auth()->id(),
]);
```

## Custom State Column

Override `getStateColumn()` if your column is not `state`:

```php
protected function getStateColumn(): string
{
    return 'status';
}
```

## Integer Backed Enums

Both string and integer backed enums are supported:

```php
enum Priority: int
{
    case Low = 0;
    case Medium = 1;
    case High = 2;
}
```

## Serialization

State machines can be serialized for caching:

```php
$array = $model->getStateMachine()->toArray();
$restored = StateMachine::fromArray($array);
```

## Concurrency Safety

Transitions use atomic database updates with a WHERE clause on the current state, wrapped in a database transaction. If the state was modified between reading and writing, an `InvalidStateTransitionException` is thrown.

## API Reference

### HasStateMachine Trait

| Method | Returns | Description |
|--------|---------|-------------|
| `transitionTo(BackedEnum $state, array $data = [])` | `bool` | Transition to new state |
| `canTransitionTo(BackedEnum $state)` | `bool` | Check if transition is allowed |
| `getAllowedTransitions()` | `array` | Get valid target states |
| `isInFinalState()` | `bool` | Check if current state is terminal |
| `getStateMachine()` | `StateMachine` | Get the compiled state machine |
| `scopeWhereState(Builder, BackedEnum ...)` | `void` | Query scope: filter by state |
| `scopeWhereNotState(Builder, BackedEnum ...)` | `void` | Query scope: exclude by state |

### StateMachine

| Method | Returns | Description |
|--------|---------|-------------|
| `canTransition(BackedEnum $from, BackedEnum $to, ?Model $model = null)` | `bool` | Check transition validity |
| `getTransitions(BackedEnum $from)` | `array` | Get allowed targets from state |
| `isFinal(BackedEnum $state)` | `bool` | Check if state is terminal |
| `getSourceStates(BackedEnum $target)` | `array` | Get states that can reach target |
| `getAllStates()` | `array` | Get all states in the machine |
| `toArray()` | `array` | Serialize for caching |
| `fromArray(array $data)` | `StateMachine` | Restore from serialized data |

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## License

MIT
