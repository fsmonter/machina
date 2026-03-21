# Machina

Enum-powered state machines for Laravel.

## Installation

```bash
composer require fsmonter/machina
```

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

### 2. Define a state machine

```php
use Machina\StateMachineBuilder;
use Machina\StateMachineCast;

class OrderStateCast extends StateMachineCast
{
    protected string $enum = OrderState::class;

    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->from(OrderState::Pending)
                ->on('process')->to(OrderState::Processing)
                    ->guard(fn (Order $order) => $order->total > 0)
                    ->do(fn (Order $order) => $order->notify(new OrderProcessing))
                ->on('cancel')->to(OrderState::Cancelled)

            ->from(OrderState::Processing)
                ->on('complete')->to(OrderState::Completed)
                    ->do(fn (Order $order) => $order->update(['completed_at' => now()]))
                ->on('fail')->to(OrderState::Failed)

            ->final(OrderState::Completed, OrderState::Failed, OrderState::Cancelled);
    }
}
```

### 3. Apply the cast to your model

```php
class Order extends Model
{
    protected $casts = [
        'state' => OrderStateCast::class,
    ];
}
```

### 4. Use it

```php
$order = Order::create(['state' => OrderState::Pending]);

// Send operations by name
$order->state->send('process');

// Or use magic methods
$order->state->process();

// Check if an operation is available
$order->state->canSend('complete');  // true
$order->state->canComplete();        // true

// List all available operations from the current state
$order->state->availableOperations(); // ['complete', 'fail']
```

## Operations

Operations are the primary way to interact with state machines. They let you declare intent ("process this order") rather than specify target states ("go to processing").

```php
machina()
    ->from(LeadStatus::New)
        ->on('contact')->to(LeadStatus::Contacted)
            ->do(fn (Lead $lead) => $lead->logActivity('First contact made'))
        ->on('disqualify')->to(LeadStatus::Lost)

    ->from(LeadStatus::Contacted)
        ->on('qualify')->to(LeadStatus::Qualified)
            ->guard(fn (Lead $lead) => $lead->email !== null)
            ->do(fn (Lead $lead) => $lead->notify(new LeadQualified))
        ->on('sms')->do(fn (Lead $lead) => app(SmsService::class)->send($lead))
        ->on('lose')->to(LeadStatus::Lost)

    ->final(LeadStatus::Won, LeadStatus::Lost)
```

**`on(string $name)`** defines an operation under the current `from()` state.

**`to(BackedEnum $state)`** sets the target state for the operation. The transition is registered in the graph automatically.

**`guard(Closure $guard)`** adds a condition that must pass before the operation can execute.

**`do(Closure $action)`** attaches a side-effect that runs after the transition completes.

### Execution order

For operations with a transition: guard evaluation, atomic state transition, then `do()` closure.

For state-bound operations (no `to()`): guard evaluation, then `do()` closure. The state does not change.

### State-bound operations

An operation without `to()` runs its `do()` closure without changing state. Useful for actions that belong to a specific state but don't trigger a transition:

```php
->from(OrderState::Processing)
    ->on('sendUpdate')->do(fn (Order $order) => $order->notifyCustomer())
```

## Direct Transitions

You can also transition directly without operations:

```php
$order->state->transitionTo(OrderState::Processing);
$order->state->canTransitionTo(OrderState::Processing); // bool
$order->state->allowedTransitions(); // [OrderState::Processing, OrderState::Cancelled]
```

Direct transitions work with the standard builder syntax:

```php
machina()
    ->from(OrderState::Pending)->to(OrderState::Processing, OrderState::Cancelled)
    ->from(OrderState::Processing)->to(OrderState::Completed, OrderState::Failed)
    ->final(OrderState::Completed, OrderState::Failed, OrderState::Cancelled)
```

## Transition Guards

Guards add conditions to transitions. All guards must pass for the transition to proceed:

```php
->from(OrderState::Pending)->to(OrderState::Processing)
    ->guard(fn (Order $order) => $order->total > 0)
    ->guard(fn (Order $order) => $order->items()->exists())
```

When used with operations, guards are attached to the operation:

```php
->from(OrderState::Pending)
    ->on('process')->to(OrderState::Processing)
        ->guard(fn (Order $order) => $order->total > 0)
```

When a guard fails, `canTransitionTo()` / `canSend()` returns false and `transitionTo()` / `send()` throws `InvalidStateTransitionException`.

## Events

A `StateTransitioned` event fires after every successful transition:

```php
use Machina\Events\StateTransitioned;

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

To use a custom event class, override `eventClass()` in your cast:

```php
protected function eventClass(): string
{
    return OrderStateChanged::class;
}
```

## Additional Data

Pass extra columns to update alongside the state transition:

```php
$order->state->transitionTo(OrderState::Processing, [
    'processed_at' => now(),
    'processor_id' => auth()->id(),
]);

// Also works with send()
$order->state->send('process', [
    'processed_at' => now(),
]);
```

## State Introspection

```php
$order->state->value();         // OrderState::Pending (the enum)
$order->state->is(OrderState::Pending); // true
$order->state->isFinal();       // false
$order->state->stateMachine();  // StateMachine instance
(string) $order->state;         // "pending"
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

State machines can be serialized for caching (transitions and final states only; guards and operations contain closures and are not serializable):

```php
$array = $order->state->stateMachine()->toArray();
$restored = StateMachine::fromArray($array);
```

## Concurrency Safety

Transitions use atomic database updates with a WHERE clause on the current state, wrapped in a database transaction. If the state was modified between reading and writing, an `InvalidStateTransitionException` is thrown.

## API Reference

### State

| Method | Returns | Description |
|--------|---------|-------------|
| `send(string $operation, array $data = [])` | `bool` | Execute a named operation |
| `canSend(string $operation)` | `bool` | Check if an operation is available |
| `availableOperations()` | `array` | List operations available from current state |
| `transitionTo(BackedEnum $state, array $data = [])` | `bool` | Direct transition to a state |
| `canTransitionTo(BackedEnum $state)` | `bool` | Check if direct transition is allowed |
| `allowedTransitions()` | `array` | Get valid target states |
| `isFinal()` | `bool` | Check if current state is terminal |
| `value()` | `BackedEnum` | Get the current state enum |
| `is(BackedEnum $state)` | `bool` | Compare against a state |
| `stateMachine()` | `StateMachine` | Get the compiled state machine |

### StateMachine

| Method | Returns | Description |
|--------|---------|-------------|
| `canTransition(BackedEnum $from, BackedEnum $to, ?Model $model = null)` | `bool` | Check transition validity |
| `getTransitions(BackedEnum $from)` | `array` | Get allowed targets from state |
| `findOperation(BackedEnum $from, string $name)` | `?Operation` | Look up an operation |
| `canSend(BackedEnum $from, string $name, ?Model $model = null)` | `bool` | Check if operation is available |
| `getOperations(BackedEnum $from)` | `array` | Get all operations for a state |
| `isFinal(BackedEnum $state)` | `bool` | Check if state is terminal |
| `getSourceStates(BackedEnum $target)` | `array` | Get states that can reach target |
| `getAllStates()` | `array` | Get all states in the machine |
| `toArray()` | `array` | Serialize for caching |
| `fromArray(array $data)` | `StateMachine` | Restore from serialized data |

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13

## License

MIT
