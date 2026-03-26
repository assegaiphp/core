# Events and Domain Events

Events are a simple way to say:

"this happened, and other parts of the application may care."

That is useful when you want to keep one piece of code focused on its own job instead of making it directly call every downstream action itself.

For example:

- an order is created
- a user signs up
- a password is reset
- a report finishes generating

The code that performs the main action can emit an event, and other listeners can react to it.

## When to use events

Use events when:

- one action can lead to several follow-up actions
- those follow-up actions should stay loosely coupled
- the follow-up work still belongs in the same PHP process

Examples:

- send a welcome email after a user signs up
- write an audit log after an admin changes a setting
- update a read model after an order is placed

Do not use in-process events when the work must survive process restarts or be retried later. For that, use a queue. See [Queues and Background Jobs](./queues-and-background-jobs.md).

## Install the package

In an Assegai project:

```bash
assegai add events
```

Or install it directly with Composer:

```bash
composer require assegaiphp/events
```

This package is designed to work outside Assegai too, so plain PHP projects can use it without `assegaiphp/core`.

## Standalone PHP usage

If you are not inside an Assegai app, start with the emitter directly:

```php
use Assegai\Events\EventEmitter;

$events = new EventEmitter();

$events->on('orders.created', function (array $payload): void {
  // send email, update a cache, write a log...
});

$events->emit('orders.created', [
  'orderId' => 42,
]);
```

This is synchronous. The listener runs immediately during the same PHP execution.

## Assegai usage

In an Assegai app, import the events module once and inject the emitter where you want to publish events.

```php
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Events\Assegai\AssegaiEventEmitter;
use Assegai\Events\Assegai\EventsModule;

#[Injectable]
final class OrdersService
{
  public function __construct(
    private readonly AssegaiEventEmitter $events,
  )
  {
  }

  public function create(array $order): void
  {
    // save the order first

    $this->events->emit('orders.created', $order);
  }
}

#[Module(
  imports: [EventsModule::class],
  providers: [OrdersService::class],
)]
final class AppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}
```

Once the module is imported, Assegai can also auto-register listener methods marked with `#[OnEvent(...)]`.

## Listening with `#[OnEvent(...)]`

Use `#[OnEvent(...)]` on a provider method when you want it to react to an event:

```php
use Assegai\Core\Attributes\Injectable;
use Assegai\Events\Attributes\OnEvent;

#[Injectable]
final class OrderListener
{
  #[OnEvent('orders.created')]
  public function handleOrderCreated(array $payload): void
  {
    // send confirmation email
    // update analytics
    // write audit entries
  }
}
```

That listener provider should be registered in a module like any other provider:

```php
#[Module(
  imports: [EventsModule::class],
  providers: [OrdersService::class, OrderListener::class],
)]
final class OrdersModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}
```

## Named events vs event objects

You can emit either:

1. a named event such as `orders.created`
2. an event object such as `new OrderCreated(...)`

Named events are easy to read and easy to filter with wildcards:

```php
$events->emit('orders.created', ['orderId' => 42]);
```

Event objects are useful when you want a typed payload:

```php
final readonly class OrderCreated
{
  public function __construct(
    public int $orderId,
    public string $customerEmail,
  )
  {
  }
}

$events->emit(new OrderCreated(42, 'orders@example.com'));
```

Then a listener can receive the typed object directly:

```php
#[OnEvent(OrderCreated::class)]
public function handle(OrderCreated $event): void
{
  // $event->orderId
  // $event->customerEmail
}
```

## Wildcards

The emitter supports wildcard event names by default.

Use `*` to match one segment:

```php
#[OnEvent('orders.*')]
public function handleOrderEvents(mixed $payload, string $eventName): void
{
  // matches orders.created
  // matches orders.cancelled
  // does not match orders.delivery.failed
}
```

Use `**` to match multiple levels:

```php
#[OnEvent('orders.**')]
public function handleAnyOrderEvent(mixed $payload, string $eventName): void
{
  // matches orders.created
  // matches orders.delivery.failed
}
```

## What gets passed to the listener

The emitter adapts the arguments based on the listener method signature:

- no parameters: nothing is passed
- one parameter: the payload or event object
- two parameters: payload/event object, then the event name
- three parameters: payload/event object, event name, then the original event object if one was emitted

Example:

```php
#[OnEvent('orders.created')]
public function handle(array $payload, string $eventName): void
{
  // $payload contains the emitted data
  // $eventName is "orders.created"
}
```

## Readiness and early events

Assegai registers `#[OnEvent(...)]` listeners during application bootstrap.

That means events emitted too early can be missed, especially if they are fired before bootstrap finishes.

If you need to emit during startup code, wait for the readiness watcher first:

```php
use Assegai\Events\Assegai\AssegaiEventEmitter;
use Assegai\Events\Assegai\EventEmitterReadinessWatcherProvider;

final class StartupPublisher
{
  public function __construct(
    private readonly EventEmitterReadinessWatcherProvider $eventsReady,
    private readonly AssegaiEventEmitter $events,
  )
  {
  }

  public function boot(): void
  {
    $this->eventsReady->waitUntilReady();

    $this->events->emit('app.started');
  }
}
```

Most day-to-day feature code will not need this. It mainly matters for startup-time events.

## A practical pattern

The usual pattern looks like this:

1. a service performs the main action
2. the service emits a past-tense event such as `orders.created`
3. one or more listeners react to that event

That helps keep the main service focused:

- `OrdersService` creates the order
- `OrderEmailListener` sends email
- `OrderAuditListener` writes audit records
- `OrderProjectionListener` updates a read model

Those listeners do not need to know about each other.

## Good naming habits

Good event names usually describe something that has already happened:

- `users.registered`
- `orders.created`
- `invoices.sent`

That reads more clearly than command-style names such as:

- `send.invoice`
- `update.analytics`

The event should describe the fact. The listener decides what to do about that fact.

## What this package does not do

This package is intentionally small.

Today it gives you:

- synchronous event emission
- listener registration
- `#[OnEvent(...)]` support
- wildcard event matching
- a small Assegai bridge

It does not try to be:

- a queue system
- a distributed event bus
- an event store
- a retry engine

If you need durable background processing, move the work onto a queue after the event handler decides that it should happen.

## Next step

If you want the deeper details around wildcard behavior, listener readiness, package configuration, and how to keep events maintainable as the app grows, continue with [Events In Depth](./events-and-domain-events-in-depth.md).
