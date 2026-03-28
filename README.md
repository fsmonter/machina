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
use Machina\StateMachineBuilder;

class OrderStateMachine extends Machina
{
    public function transitions(): StateMachineBuilder
    {
        return machina()
            ->initial(OrderState::Pending)
            ->on('process',
                from: OrderState::Pending,
                to: OrderState::Processing,
                guard: fn (Order $order) => $order->total > 0,
                action: fn (Order $order) => $order->notify(new OrderProcessing)
            )
            ->on('cancel', from: OrderState::Pending, to: OrderState::Cancelled)
            ->on('complete',
                from: OrderState::Processing,
                to: OrderState::Completed,
                action: fn (Order $order) => $order->update(['completed_at' => now()])
            )
            ->on('fail', from: OrderState::Processing, to: OrderState::Failed)
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
$order = Order::create(); // state auto-set to 'pending'

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

Operations are the primary way to interact with state machines. They let you declare intent ("process this order") rather than specify target states ("go to processing").

```php
machina()
    ->initial(LeadStatus::New)
    ->on('contact',
        from: LeadStatus::New,
        to: LeadStatus::Contacted,
        action: fn (Lead $lead) => $lead->logActivity('First contact made')
    )
    ->on('disqualify', from: LeadStatus::New, to: LeadStatus::Lost)
    ->on('qualify',
        from: LeadStatus::Contacted,
        to: LeadStatus::Qualified,
        guard: fn (Lead $lead) => $lead->email !== null,
        action: fn (Lead $lead) => $lead->notify(new LeadQualified)
    )
    ->on('sms',
        from: LeadStatus::Contacted,
        action: fn (Lead $lead) => app(SmsService::class)->send($lead)
    )
    ->on('lose', from: LeadStatus::Contacted, to: LeadStatus::Lost)
    ->final(LeadStatus::Won, LeadStatus::Lost)
```

**`on(string $name, from:, to:, guard:, action:)`** defines a named operation.

- `from:` (required) the source state
- `to:` (optional) the target state. Omit for state-bound operations
- `guard:` (optional) a Closure or array of Closures that must all return true
- `action:` (optional) a Closure that runs after the transition completes

### Execution order

For operations with a transition: guard evaluation, atomic state transition, then `action` closure.

For state-bound operations (no `to:`): guard evaluation, then `action` closure. The state does not change.

Note: `action` runs after the database transaction commits. If the closure throws, the state change is already persisted.

### State-bound operations

An operation without `to:` runs its `action` closure without changing state. Useful for actions that belong to a specific state but don't trigger a transition:

```php
->on('sendUpdate',
    from: OrderState::Processing,
    action: fn (Order $order) => $order->notifyCustomer()
)
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

`on()` and `transition()` can be mixed on the same builder.

## Transition Guards

Guards add conditions to transitions. All guards must pass for the transition to proceed:

```php
->transition(from: OrderState::Pending, to: OrderState::Processing,
    guard: [
        fn (Order $order) => $order->total > 0,
        fn (Order $order) => $order->items()->exists(),
    ])
```

On operations, guards are passed inline:

```php
->on('process',
    from: OrderState::Pending,
    to: OrderState::Processing,
    guard: fn (Order $order) => $order->total > 0
)
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

## Concurrency Safety

Transitions use atomic database updates with a WHERE clause on the current state, wrapped in a database transaction using the model's own connection. If the state was modified between reading and writing, an `InvalidStateTransitionException` is thrown.

Note: Eloquent model events (`saving`, `updated`, etc.) are not fired during transitions. The raw query is intentional for atomicity.

## License

MIT
