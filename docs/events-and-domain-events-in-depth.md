# Events In Depth

Continue here after [Events and Domain Events](./events-and-domain-events.md).

The goal here is not to show the first happy path again. The goal is to help you design events that stay useful once an application grows.

## The mental model

An event is a record that something already happened.

That sounds small, but it leads to a very helpful design rule:

- services perform work
- events describe the outcome
- listeners react to the outcome

That separation keeps your application easier to change.

## Event names should describe facts

Prefer event names that read like completed facts:

- `users.registered`
- `orders.created`
- `orders.paid`
- `reports.generated`

Avoid names that sound like commands:

- `send.welcome.email`
- `notify.billing`
- `build.analytics`

Command-style names often couple the event too closely to one listener. Fact-style names leave room for more listeners later.

## Choosing between arrays and typed event objects

Both styles are supported.

### Arrays are useful when:

- the payload is small
- the event is local to one feature
- you want the lightest possible setup

```php
$events->emit('orders.created', [
  'orderId' => 42,
  'customerEmail' => 'orders@example.com',
]);
```

### Typed event objects are useful when:

- the event will be used in several places
- the payload deserves stronger structure
- you want IDE support and clear constructor rules

```php
final readonly class OrderCreated
{
  public function __construct(
    public int $orderId,
    public string $customerEmail,
    public int $organizationId,
  )
  {
  }
}

$events->emit(new OrderCreated(
  orderId: 42,
  customerEmail: 'orders@example.com',
  organizationId: 10,
));
```

In most real apps, typed objects age better once events become part of the feature design instead of a one-off callback.

## Wildcards and namespaces

Events can be namespaced with a delimiter such as `.`:

- `orders.created`
- `orders.cancelled`
- `orders.delivery.failed`

Use `*` when you want one segment:

```php
#[OnEvent('orders.*')]
public function handleOrderEvents(mixed $payload, string $eventName): void
{
}
```

This matches:

- `orders.created`
- `orders.cancelled`

It does not match:

- `orders.delivery.failed`

Use `**` when you want multiple levels:

```php
#[OnEvent('orders.**')]
public function handleNestedOrderEvents(mixed $payload, string $eventName): void
{
}
```

This matches all of the above.

## Listener signatures

The current emitter adapts arguments in a small and predictable way:

- `fn () => ...`
- `fn ($payload) => ...`
- `fn ($payload, $eventName) => ...`
- `fn ($payload, $eventName, $eventObject) => ...`

Examples:

```php
#[OnEvent('orders.created')]
public function handle(array $payload): void
{
}
```

```php
#[OnEvent('orders.*')]
public function handle(mixed $payload, string $eventName): void
{
}
```

```php
#[OnEvent(OrderCreated::class)]
public function handle(OrderCreated $event, string $eventName, ?object $originalEvent): void
{
}
```

The first parameter is always the main thing the listener is expected to care about.

## Listener scope in Assegai

The current Assegai bridge auto-registers listener methods from application-scoped providers during bootstrap.

That means:

- application-scoped providers are the normal path for `#[OnEvent(...)]` listeners
- request-scoped listeners are intentionally skipped during bootstrap registration

Why?

Because request-scoped providers are created for one request at a time, while event listeners need a stable registration point during application startup.

If a listener depends on request-only state, it is usually a sign that the work belongs in the request pipeline instead of the event system.

## Readiness and early emits

This package follows the same general caution you see in the NestJS events workflow: if you emit before declarative listeners are registered, that event can be missed.

In practice, that mostly affects:

- constructor-time emits
- bootstrap-time emits
- `onModuleInit`-style startup logic

When that matters, wait for readiness:

```php
$this->eventsReady->waitUntilReady();
$this->events->emit('app.started');
```

For ordinary controller and service code, this is usually not needed.

## Error handling

By default, listener errors bubble up.

That is usually the right default because it keeps failures visible while a feature is being built.

If you intentionally want one listener to fail without interrupting the emitter, use the suppress-errors option on the attribute:

```php
#[OnEvent('orders.created', suppressErrors: true)]
public function handle(array $payload): void
{
  // best-effort side effect
}
```

Use that carefully. If a side effect is truly important, it is often better to let the failure surface or move the work to a durable queue.

If you want to observe listener failures for logging, metrics, or alerts, attach a failure hook:

```php
use Assegai\Events\EventListenerFailure;

$events->onFailure(function (EventListenerFailure $failure): void {
  logger()->error('Event listener failed.', [
    'event' => $failure->eventName,
    'listener' => $failure->listenerId,
    'message' => $failure->throwable->getMessage(),
    'suppressed' => $failure->suppressed,
  ]);
});
```

Failure hooks are observational. They do not replace the normal exception policy.

## Events vs queues

This is the most important boundary to understand.

### Use an event when:

- the work can happen immediately
- it is acceptable for the work to run in the current process
- you mainly want decoupling

### Use a queue when:

- the work should be retried
- the work may be slow
- the work should survive a crashed request
- a worker may process it later

A common pattern is:

1. emit an event
2. let one listener decide whether a queue job should be created

That keeps the main feature code clean while still giving you durable background processing where it matters.

## Outbox-first durable events

If you need stronger guarantees than in-process events can provide, an outbox is the safest next step.

The package still exposes a small generic abstraction:

```php
use Assegai\Events\Interfaces\DurableOutboxStoreInterface;
use Assegai\Events\Outbox\OutboxMessage;
use Assegai\Events\Outbox\OutboxRecorder;
use DateTimeImmutable;
use Throwable;

final class DatabaseOutboxStore implements DurableOutboxStoreInterface
{
  public function append(OutboxMessage $message): void
  {
    // persist to a durable store
  }

  public function leasePending(int $limit = 100, ?DateTimeImmutable $now = null): array
  {
    return [];
  }

  public function markDispatched(string|int $id, ?DateTimeImmutable $dispatchedAt = null): void
  {
  }

  public function markFailed(string|int $id, string|Throwable $error, ?DateTimeImmutable $retryAt = null): void
  {
  }
}

$outbox = new OutboxRecorder(new DatabaseOutboxStore());
$outbox->record(
  new OrderCreated(orderId: 42, customerEmail: 'orders@example.com', organizationId: 10),
  headers: ['source' => 'checkout'],
);
```

For Assegai projects there is now a ready-made bridge:

- `EventsOutboxModule` adds the durable bridge providers
- `OrmOutboxStore` persists messages into the `event_outbox` table
- `AssegaiOutboxRelayService` leases pending rows and publishes them to the queue connection configured in `assegai.json`

Example configuration:

```json
{
  "events": {
    "outbox": {
      "queue": "rabbitmq.events",
      "batchSize": 100,
      "retryDelaySeconds": 60
    }
  }
}
```

Example module import:

```php
use Assegai\Events\Assegai\Outbox\EventsOutboxModule;

#[Module(
  imports: [EventsOutboxModule::class],
)]
final class AppModule
{
}
```

Example relay usage:

```php
use Assegai\Core\Attributes\Injectable;
use Assegai\Events\Assegai\Outbox\AssegaiOutboxRelayService;

#[Injectable]
final class OutboxDrainService
{
  public function __construct(
    private readonly AssegaiOutboxRelayService $relay,
  )
  {
  }

  public function flush(): void
  {
    $this->relay->relayPending();
  }
}
```

That gives you a cleaner production story:

1. write domain data
2. append an outbox message in the same transaction
3. let a worker publish or queue it later

Use plain in-process events for decoupling inside one request. Use an outbox or queue when delivery guarantees matter.

One important boundary: the ORM-backed store gives you a real durable table and relay flow, but strict one-transaction outbox guarantees still depend on how your application manages database transactions. If you need the domain write and outbox append to share the exact same transaction, build the store around a repository or manager that participates in that same unit of work.

## Package configuration in Assegai

The Assegai bridge reads its config from `assegai.json` under `events`.

Example:

```json
{
  "events": {
    "wildcards": true,
    "delimiter": ".",
    "maxListeners": 25
  }
}
```

Current supported options are:

- `wildcards`
- `delimiter`
- `maxListeners`
- `outbox.queue`
- `outbox.batchSize`
- `outbox.retryDelaySeconds`

If the section is missing, the package falls back to sensible defaults.

## A realistic feature example

Here is a simple pattern for order creation:

```php
final readonly class OrderCreated
{
  public function __construct(
    public int $orderId,
    public int $organizationId,
    public string $customerEmail,
  )
  {
  }
}
```

```php
#[Injectable]
final class OrdersService
{
  public function __construct(
    private readonly AssegaiEventEmitter $events,
  )
  {
  }

  public function create(array $input): void
  {
    // persist order

    $this->events->emit(new OrderCreated(
      orderId: 42,
      organizationId: 10,
      customerEmail: 'orders@example.com',
    ));
  }
}
```

```php
#[Injectable]
final class OrderEmailListener
{
  #[OnEvent(OrderCreated::class)]
  public function sendConfirmation(OrderCreated $event): void
  {
    // send email
  }
}
```

```php
#[Injectable]
final class OrderProjectionListener
{
  #[OnEvent(OrderCreated::class)]
  public function updateReadModel(OrderCreated $event): void
  {
    // update reporting table
  }
}
```

The service stays focused on creating the order. The listeners stay focused on side effects.

## Keep the event layer boring

Good event systems usually feel boring:

- simple names
- clear payloads
- listeners with one responsibility
- no request-specific state
- no hidden retries or distributed behavior pretending to be local

That is a good thing.

If you can explain an event in one sentence and tell exactly why each listener exists, the design is probably healthy.
