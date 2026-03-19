# OpenSwoole Runtime and Microservices Plan

This document is an internal architecture note for future work.

It describes a realistic path for bringing OpenSwoole into AssegaiPHP without pretending the work is already done.

## Why this matters

OpenSwoole can unlock several things that are hard to do well in the current request-per-process model:

- long-lived workers
- non-blocking I/O
- WebSockets and streaming
- lower overhead for high-concurrency HTTP workloads
- a cleaner foundation for message-based microservices later

The key point is that OpenSwoole should be treated as a new runtime target, not just a different way to call `App::run()`.

## What exists today

The current framework is shaped around a traditional PHP request lifecycle:

- one request comes in
- framework state is initialized
- modules, providers, controllers, and middleware are resolved
- the request is handled
- the process ends or resets naturally

That model is a good fit for PHP's built-in server, Apache, and PHP-FPM. It is not yet a safe fit for a long-lived coroutine runtime.

## Main blockers in the current code

The most important blockers are global and singleton-style state.

Examples in the current codebase:

- [core/src/AssegaiFactory.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/AssegaiFactory.php) creates the app with singleton instances of the router, controller manager, module manager, and injector.
- [core/src/Http/Requests/Request.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/Http/Requests/Request.php) exposes a singleton request object.
- [core/src/Http/Responses/Response.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/Http/Responses/Response.php) exposes a singleton response object.
- [core/src/Routing/Router.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/Routing/Router.php) repeatedly reaches for global `Request::getInstance()` and `Response::getInstance()`.
- [core/src/Rendering/Engines/DefaultTemplateEngine.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/Rendering/Engines/DefaultTemplateEngine.php) also reads request state through the global request singleton.
- [core/src/App.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/App.php) builds framework state with the assumption that the application lifecycle is effectively request-scoped.

Those patterns are workable in a short-lived model. In OpenSwoole they create a serious risk of state leaking between requests or coroutines.

## Design goal

The end goal is not "make the current HTTP runtime faster."

The end goal is:

- keep the current runtime working as it does today
- add an OpenSwoole runtime beside it
- move framework state toward explicit request scope
- make queue workers, WebSockets, and later microservices fit into the same runtime model

This should feel closer to NestJS in spirit:

- one framework
- multiple runtime modes
- shared module and provider model
- transport-specific adapters on top

## Proposed phases

## Phase 1: Introduce runtime abstraction

Before adding OpenSwoole directly, `core` needs a runtime seam.

The framework should gain a small runtime contract with responsibilities such as:

- boot the app
- create per-request execution context
- adapt incoming transport data into `Request`
- emit the final `Response`

Suggested shape:

- `HttpRuntimeInterface`
- `PhpBuiltinRuntime`
- `OpenSwooleHttpRuntime`

The current built-in server path should become one runtime implementation rather than the only implicit runtime.

### Expected outcome

- existing apps keep working unchanged
- the framework stops assuming there is only one request execution model

## Phase 2: Separate application scope from request scope

This is the most important refactor.

Framework state should be split into:

- application-wide services that are safe to reuse
- request-scoped state that must be created fresh per request

That means moving away from direct singleton access for:

- `Request`
- `Response`
- execution context
- route-bound request metadata

### Practical direction

- keep `ModuleManager`, route metadata, and compiled declarations application-scoped
- make `Request`, `Response`, and anything that depends on the active client request explicitly request-scoped
- stop reading request state through global helpers inside rendering and routing layers when an injected request object can be used instead

### Expected outcome

- no request data leaks between concurrent requests
- the framework becomes safe for long-lived workers

## Phase 3: Prebuild application graph once, execute many requests

A long-lived runtime should not rebuild everything on every request if it does not need to.

Today, [core/src/App.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/App.php) resolves modules, providers, declarations, controllers, and middleware inside the run cycle. That is reasonable for the current model, but it is not ideal for OpenSwoole.

The OpenSwoole runtime should move toward:

- build module graph once
- build provider graph once where safe
- resolve request-scoped dependencies per request
- dispatch handlers with request-local context only

### Expected outcome

- better throughput
- much lower redundant reflection and bootstrap work

## Phase 4: Add OpenSwoole HTTP server support

Once request scope is safe, the HTTP runtime can be added.

This should include:

- OpenSwoole server bootstrap
- mapping OpenSwoole request data into Assegai request objects
- mapping Assegai responses back to OpenSwoole responses
- graceful shutdown hooks
- worker start hooks
- configuration for worker count, task workers, host, and port

### CLI shape

The CLI should likely support one of these forms:

```bash
assegai serve --runtime=openswoole
```

or later:

```bash
assegai start
assegai start --runtime=openswoole
```

The current `serve` command can remain the easy default for traditional local development until the OpenSwoole path is stable.

## Phase 5: Async-capable framework services

OpenSwoole only pays off fully if framework integrations can avoid blocking.

That means future work in packages around:

- database access
- queues
- HTTP clients
- cache and Redis access
- WebSocket/event flows

This phase should be approached carefully. Supporting OpenSwoole as a runtime does not mean every package becomes truly async on day one.

### Practical rule

First make the runtime safe.

Then make the hot paths async-aware where it gives real value.

## Phase 6: Message-based microservices

Microservices should come after the OpenSwoole runtime is stable, not before.

The ideal shape is transport adapters over the same module and provider model.

That could eventually include concepts similar to NestJS:

- message pattern handlers
- event pattern handlers
- transport adapters
- TCP or queue-based message servers
- client proxies

Possible future transports:

- TCP
- Redis pub/sub
- RabbitMQ
- HTTP internal RPC

### Important constraint

Microservices should not require a separate mental model from the main framework.

A controller handles HTTP.
A message handler handles a transport message.
Providers still contain application logic in both cases.

## Queue integration direction

The recent `queue:list` and `queue:work` CLI flow should stay runtime-agnostic.

That means:

- queue producers should work in both traditional and OpenSwoole runtimes
- queue processors should not depend on HTTP runtime details
- OpenSwoole task workers can be explored later, but should not replace explicit queue backends too early

OpenSwoole task workers may be useful for short-lived internal offloading, but they are not a substitute for durable queue infrastructure.

## Suggested package layout

To keep the core package from becoming too entangled, the eventual shape could look like:

- `assegaiphp/core`
  - runtime contracts
  - default HTTP runtime
- `assegaiphp/openswoole`
  - OpenSwoole HTTP runtime
  - OpenSwoole server bootstrap
  - OpenSwoole-specific adapters
- future transport packages
  - `assegaiphp/microservices`
  - `assegaiphp/websockets`

This keeps the base framework usable without forcing OpenSwoole on every app.

## Minimal viable implementation order

If work starts soon, the smallest responsible sequence is:

1. introduce runtime contracts in `core`
2. move request and response state away from global singleton access
3. make routing and rendering consume explicit request-scoped objects
4. prebuild the application graph safely
5. add an OpenSwoole HTTP runtime package
6. add CLI support for starting that runtime
7. only then begin transport-level microservice work

## Things to avoid

- do not bolt OpenSwoole directly into the current `App::run()` flow without isolating request state
- do not market microservices before the runtime model is stable
- do not make async a requirement for every Assegai package immediately
- do not mix durable queue workflows with in-memory task workers as if they solve the same problem

## What we can say publicly today

Reasonable public wording today would be:

- Assegai is exploring an OpenSwoole runtime for long-lived workers and future async capabilities.
- The current framework remains request-oriented and the OpenSwoole path is still a design and implementation effort.
- Microservices are a future direction, not a shipped feature.

That keeps expectations honest while still making the direction clear.
