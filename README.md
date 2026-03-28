# Machina

Enum-powered state machines for Laravel.

## Installation

```bash
composer require fsmonter/machina
```

## Quick Start

### 1. Define states as a backed enum

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
use Machina\Machina;
use Machina\StateBuilder;
use Machina\StateMachineBuilder;

class OrderStateMachine extends Machina
{
    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->initial(OrderState::Pending)
            ->state(OrderState::Pending, function (StateBuilder $state) {
                $state->on('process')
                    ->target(OrderState::Processing)
                    ->guard(fn (Order $order) => $order->total > 0)
                    ->action(fn (Order $order) => $order->notify(new OrderProcessing));
                $state->on('cancel')->target(OrderState::Cancelled);
            })
            ->state(OrderState::Processing, function (StateBuilder $state) {
                $state->on('complete')
                    ->target(OrderState::Completed)
                    ->action(fn (Order $order) => $order->update(['completed_at' => now()]));
                $state->on('fail')->target(OrderState::Failed);
            })
            ->final(OrderState::Completed, OrderState::Failed, OrderState::Cancelled);
    }
}
```

### 3. Add the trait to your model

```php
use Machina\HasStateMachine;

class Order extends Model
{
    use HasStateMachine;

    protected $stateMachines = [
        'state' => OrderStateMachine::class,
    ];
}
```

### 4. Use it

```php
$order = Order::create(); // state is auto-set to 'pending'

// Get current state enum
$order->state->current(); // OrderState::Pending

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

## Initial State

Define an initial state with `initial()`. When a model is created without an explicit state value, the initial state is set automatically:

```php
Order::create();                                // state = OrderState::Pending
Order::create(['state' => OrderState::Failed]); // state = OrderState::Failed (not overridden)
```

## Operations

Operations are the primary way to interact with state machines. They let you declare intent ("approve this transaction") rather than specify target states ("go to approved").

```php
machina()
    ->initial(TransactionState::Pending)
    ->state(TransactionState::Pending, function (StateBuilder $state) {
        $state->on('approve')
            ->target(TransactionState::Approved)
            ->guard(fn (Transaction $tx) => $tx->amount <= $tx->account->balance)
            ->action(fn (Transaction $tx) => $tx->account->debit($tx->amount));
        $state->on('reject')
            ->target(TransactionState::Rejected)
            ->action(fn (Transaction $tx) => $tx->notify(new TransactionRejected));
        $state->on('flag')
            ->action(fn (Transaction $tx) => $tx->notifyCompliance());
    })
    ->state(TransactionState::Approved, function (StateBuilder $state) {
        $state->on('settle')
            ->target(TransactionState::Settled)
            ->guard(fn (Transaction $tx) => $tx->cleared_at !== null);
    })
    ->state(TransactionState::Settled, function (StateBuilder $state) {
        $state->on('reverse')
            ->target(TransactionState::Reversed)
            ->action(fn (Transaction $tx) => $tx->account->credit($tx->amount));
    })
    ->final(TransactionState::Settled, TransactionState::Rejected, TransactionState::Reversed)
```

**`state(BackedEnum $state, Closure $callback)`** groups operations by their source state.

Inside the closure, define operations with:

- **`$state->on(string $name)`** starts an operation definition
- **`->target(BackedEnum $state)`** sets the target state
- **`->guard(Closure|array $guard)`** adds condition(s) that must pass
- **`->action(Closure $action)`** runs after the transition completes

### Execution order

For operations with a target: guard evaluation, atomic state transition, then `action` closure.

For state-bound operations (no `target()`): guard evaluation, then `action` closure. The state does not change.

Note: `action` runs after the database transaction commits. If the closure throws, the state change is already persisted.

### State-bound operations

An operation without `target()` runs its `action` closure without changing state. Useful for actions that belong to a specific state but don't trigger a transition:

```php
->state(OrderState::Processing, function (StateBuilder $state) {
    $state->on('sendUpdate')
        ->action(fn (Order $order) => $order->notifyCustomer());
})
```

## Direct Transitions

For simple state machines without named operations, use `transition()`:

```php
machina()
    ->initial(OrderState::Pending)
    ->transition(from: OrderState::Pending, to: OrderState::Processing)
    ->transition(from: OrderState::Pending, to: OrderState::Cancelled)
    ->transition(from: OrderState::Processing, to: OrderState::Completed,
        guard: fn (Order $order) => $order->isPaid())
    ->transition(from: OrderState::Processing, to: OrderState::Failed)
    ->final(OrderState::Completed, OrderState::Failed, OrderState::Cancelled)
```

You can also use direct transitions on the model:

```php
$order->state->transitionTo(OrderState::Processing);
$order->state->canTransitionTo(OrderState::Processing); // bool
$order->state->allowedTransitions(); // [OrderState::Processing, OrderState::Cancelled]
```

`state()` and `transition()` can be mixed on the same builder.

## Transition Guards

Guards add conditions to transitions. All guards must pass for the transition to proceed:

```php
->transition(from: OrderState::Pending, to: OrderState::Processing,
    guard: [
        fn (Order $order) => $order->total > 0,
        fn (Order $order) => $order->items()->exists(),
    ])
```

On operations, guards are chained:

```php
->state(OrderState::Pending, function (StateBuilder $state) {
    $state->on('process')
        ->target(OrderState::Processing)
        ->guard(fn (Order $order) => $order->total > 0);
})
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

To use a custom event class, override `eventClass()` in your state machine:

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
$order->state->current();         // OrderState::Pending (the enum)
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

## Concurrency Safety

Transitions use atomic database updates with a WHERE clause on the current state, wrapped in a database transaction using the model's own connection. If the state was modified between reading and writing, an `InvalidStateTransitionException` is thrown.

Note: Eloquent model events (`saving`, `updated`, etc.) are not fired during transitions. The raw query is intentional for atomicity.

## License

MIT
