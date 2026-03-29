# OpenSwoole Runtime

Assegai can now boot through an OpenSwoole HTTP runtime instead of the default PHP development server.

This guide is about what that means in practice, what is already safe to rely on, and where the boundary still is.

## What changes when you switch runtimes

With the default `php` runtime, each request starts from a short-lived PHP process model.

With OpenSwoole, the app runs inside long-lived workers. That changes the framework requirements in a few important ways:

- the application graph should not rebuild on every request
- request and response objects must be fresh for each request
- request-scoped providers must not leak into the next request
- shutdown and bootstrap hooks need to map to worker lifecycle events

That is why the OpenSwoole work in Assegai has focused on runtime safety before feature breadth.

## Current request lifecycle

Today, the OpenSwoole runtime does these things for each worker:

1. boot the reusable application graph once
2. prepare middleware once
3. refresh request scope for each incoming request
4. bind a runtime-specific response emitter for the active request
5. clear runtime overrides and request-scoped context after the response finishes
6. run application shutdown hooks when the worker exits

In other words, the expensive application setup is reused, but request state is not.

## Runtime configuration

The current OpenSwoole settings live under:

```json
{
  "development": {
    "server": {
      "openswoole": {
        "workerNum": 1,
        "taskWorkerNum": 0,
        "maxRequest": 0,
        "enableCoroutine": true,
        "hookFlags": "all"
      }
    }
  }
}
```

Assegai translates those into the OpenSwoole server options it knows about today:

- `workerNum` -> `worker_num`
- `taskWorkerNum` -> `task_worker_num`
- `maxRequest` -> `max_request`
- `enableCoroutine` -> `enable_coroutine`
- `hookFlags` -> `hook_flags`

## Error handling under OpenSwoole

One detail matters more in a long-lived runtime than it does in a short-lived one: failures should still go back through the framework error pipeline.

The current runtime path now does that.

If a handler throws and the throwable escapes the outer runtime callback, Assegai:

- rebuilds the active request scope from the runtime request
- routes the throwable through the normal framework exception handlers
- emits the response through the runtime-specific emitter
- clears the runtime context again after the request

That avoids the old failure mode where an alternate runtime could fall back to a raw plain-text `500` body.

## Lifecycle hooks that already work

The following application lifecycle hooks now participate in the reusable runtime path:

- `OnModuleInitInterface`
- `OnApplicationBootstrapInterface`
- `OnApplicationShutdownInterface`

At the moment, the most useful mental model is:

- module init and application bootstrap happen once when the worker boots
- application shutdown runs when the worker exits

## What is ready today

The runtime is in a good place for:

- internal testing of long-lived request handling
- local experimentation with OpenSwoole
- validating that request-scoped services do not leak across requests
- exploring where future async work should attach

## What is not ready to oversell

It is still too early to present this as a fully hardened production runtime.

The main reasons are:

- the integration coverage is still focused rather than broad
- worker management and operational tooling are still light
- ecosystem guidance for coroutine-aware integrations is still growing
- the future microservice and WebSocket story is not the same thing as “done” HTTP runtime support

So the right expectation today is:

- usable for framework development and advanced experimentation
- promising for real applications
- not yet something the docs should describe as fully production-ready

## Relationship to queues and background work

OpenSwoole does not replace queues.

Queues are still the right tool for:

- durable background work
- retries
- relay/outbox processing
- cross-service delivery

OpenSwoole task workers may become useful for some short internal offloading patterns later, but they are not a substitute for RabbitMQ, Beanstalkd, or the queue abstraction.

## Recommended workflow right now

For day-to-day project work:

```bash
assegai serve
```

When you specifically want the alternate runtime:

```bash
assegai serve --runtime=openswoole
```

That keeps the default workflow simple while making the OpenSwoole path explicit.

## Read next

If you want the broader architectural direction beyond the current runtime implementation, read [OpenSwoole Runtime and Microservices Plan](./openswoole-runtime-and-microservices-plan.md).
