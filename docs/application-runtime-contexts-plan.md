# Application Runtime Contexts Plan

This document is an internal architecture note for future work.

It describes a path for letting developers run an Assegai app across multiple runtime contexts, without pretending every workload is an HTTP request.

CLI execution is the first obvious non-web target, but it should not be the only context we think about while the runtime architecture is still expanding.

## Why this matters

Assegai apps are already more than controllers and routes.

A real project may contain:

- an HTTP API
- a web interface
- queue workers
- ETL pipelines
- schedulers
- scrapers
- maintenance commands
- import and export jobs

Those workloads often belong in the same app because they share:

- the same module graph
- the same providers
- the same data model
- the same configuration
- the same business rules

Today, Assegai has a meaningful HTTP runtime story, but not a first-class runtime-context story.

That creates friction for developers who want to build one coherent app that can:

- serve requests
- run background or operational workflows
- support long-lived runtimes cleanly
- evolve into schedulers, workers, or transport-specific execution later

## Example use case

A cinema aggregation app might include:

- an API serving movie showtimes and cinema details
- a web UI showing the same information
- a periodic ETL pipeline scraping different sources depending on the cinema

That ETL pipeline should be able to run as part of the app, using the same providers and configuration, without having to pretend it is a web request or live in a totally separate codebase.

## Why now

OpenSwoole is exactly the right moment to think more broadly.

Once we accept that Assegai should support more than one HTTP runtime, it is a short step to the larger question:

> should Assegai really model runtime as "HTTP only", or should it model runtime as "application execution context"?

The second framing is the stronger long-term architecture.

## Current limitation

The runtime seam in `core` is still HTTP-shaped.

Relevant files:

- [HttpRuntimeInterface.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/Interfaces/HttpRuntimeInterface.php)
- [App.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/App.php)
- [AssegaiFactory.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/AssegaiFactory.php)

Right now:

- `App::run()` delegates to `HttpRuntimeInterface`
- the default lifecycle is `runDefaultHttpLifecycle()`
- request and response objects are core parts of the active runtime context
- runtime selection in the factory only knows `php` and `openswoole`

That is fine for web execution.

It is not the right abstraction for:

- `assegai run etl:scrape`
- `assegai run data:refresh`
- `assegai run import:cinema-feed`
- future scheduler and worker surfaces

If we force those flows through HTTP-shaped abstractions, we will create awkward APIs and fragile internals.

## Design goal

The goal is not "make the CLI feel like fake HTTP."

The goal is:

- keep the current HTTP runtime story working
- let Assegai support multiple runtime contexts cleanly
- let developers run an Assegai app as a program when the workload is non-web
- reuse modules, providers, lifecycle hooks, and configuration
- give each runtime context its own execution context

The app should feel like one application with multiple runtime contexts.

## Product direction

From a developer experience point of view, the first non-web UX should feel something like:

```bash
assegai run etl:scrape
assegai run etl:scrape --cinema=ster-kinekor
assegai run data:refresh --once
assegai run job:process-showtimes
```

That is the first concrete UX target.

The broader runtime direction should eventually cover contexts such as:

- HTTP over the default PHP runtime
- HTTP over OpenSwoole
- CLI command execution
- long-running workers
- schedulers
- queue or transport-driven execution later

## Principles

### 1. Do not weaken the HTTP runtime to get broader runtime support

The new work should generalize runtime architecture, not regress the web path.

### 2. Non-web execution must not depend on fake request objects

A CLI app should not have to manufacture:

- `Request`
- `Response`
- `ResponseEmitter`

just to run a scraper or ETL job.

### 3. Shared application logic should stay in providers

Controllers, CLI jobs, queue processors, and later schedulers should all consume the same service layer.

### 4. Developers should be able to choose the right execution surface

For example:

- HTTP controller for request-driven workflows
- CLI command or job entrypoint for operational workflows
- queue processor for durable background work
- scheduler or worker entrypoint for periodic or long-running workloads

### 5. This should reduce friction, not force a single style

Some teams will want app-level CLI entrypoints.
Some teams will keep certain workflows as standalone scripts.
The framework should make the integrated path attractive without making other valid workflows feel forbidden.

### 6. Do not overfit the runtime model to CLI

CLI is the first non-web product surface we should likely expose.

It should not become the accidental shape of every future non-web runtime.

## Proposed architecture

### 1. Generalize the runtime abstraction

The current [HttpRuntimeInterface.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/Interfaces/HttpRuntimeInterface.php) is too narrow for what we want next.

Recommended direction:

- introduce a more general `ApplicationRuntimeInterface`
- let HTTP runtimes implement or adapt to that broader contract
- add runtime-context-specific implementations beside the HTTP ones

Possible shape:

```php
interface ApplicationRuntimeInterface
{
  public function getName(): string;
  public function run(AppInterface $app, callable $handler): void;
}
```

Then:

- `HttpRuntimeInterface` can either extend that interface
- or HTTP runtimes can just implement the generalized one directly

The important part is that `App::run()` should no longer assume that every runtime is an HTTP runtime.

### 2. Add an explicit execution-context model

The current [RuntimeContext.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/Runtimes/RuntimeContext.php) is request-oriented in practice.

We should think in terms of runtime-specific execution contexts, for example:

- `HttpExecutionContext`
- `CliExecutionContext`
- `WorkerExecutionContext` later

That gives the framework one pattern for runtime-local state without making non-web execution pretend to be a request.

### 3. Split the lifecycle into runtime-neutral and runtime-specific parts

Right now [App.php](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/src/App.php) already has useful reusable pieces:

- `boot()`
- `shutdown()`
- request-scope refresh and cleanup
- runtime throwable handling

We should formalize that split further.

Recommended lifecycle model:

#### Runtime-neutral

- build module graph
- resolve providers
- invoke module init hooks
- invoke application bootstrap hooks
- invoke application shutdown hooks

#### HTTP-specific

- create request context
- route request
- emit response

#### CLI-specific

- create command execution context
- resolve an app-defined entrypoint
- run it
- translate success or failure into an exit code

#### Worker- or scheduler-specific later

- create a long-lived execution context
- process one unit of work or one tick safely
- flush runtime-local state between iterations
- respond cleanly to signals and shutdown

This keeps lifecycle consistency without forcing everything through one transport model.

### 4. Add a CLI execution context

The first new non-web context should be:

- `CliExecutionContext`

Suggested contents:

- entrypoint name
- raw argv
- parsed arguments
- parsed options
- working directory
- environment name
- stdin
- stdout
- stderr
- cancellation or signal status later

Developers and providers should be able to inject CLI-relevant context without any dependency on HTTP request objects.

### 5. Add app-defined non-web entrypoints

The framework needs a way for the app to declare runnable non-web entrypoints.

The first concrete version of this should likely be CLI entrypoints.

There are a few plausible directions.

#### Option A: app command classes

Example shape:

- `#[AssegaiCommand('etl:scrape')]`
- class-based handler with a `handle()` method

Pros:

- familiar to Symfony Console users
- easy to register and document
- good fit for argument parsing

#### Option B: job/task handler classes

Example shape:

- `#[Task('etl:scrape')]`
- handler invoked by the CLI runtime

Pros:

- more domain-oriented
- can also map nicely to schedulers later

#### Option C: module-level exported entrypoints

Example shape:

- module registers named app entrypoints explicitly

Pros:

- very explicit ownership
- fits Assegai's module story well

Recommended first direction:

Start with **class-based app command handlers** because they are the most approachable and easiest to wire into a CLI runtime.

We can later grow that into scheduler and worker surfaces without throwing the model away.

### 6. Keep framework CLI and app CLI distinct

This is important.

Today the `console` package already provides framework commands such as:

- `serve`
- `new`
- `add`
- `update`
- `migration:*`
- `database:*`

That is the **framework CLI**.

The new feature is about an **application runtime**.

So the separation should be:

- framework CLI commands manage or inspect projects
- app runtime commands run app-defined workloads

Recommended UX:

```bash
assegai run <entrypoint> [args...]
```

That keeps the distinction clear.

### 7. Treat CLI as the first public surface, not the only one

The initial public UX should be CLI because it is the easiest non-web execution model for developers to understand and adopt.

But the architecture should stay open to later runtime contexts instead of hardcoding "non-web = command line forever".

### 8. Reuse package extension seams

This feature should build on the package extension direction already documented in [package-extension-seams-plan.md](/home/amasiye/development/atatusoft/projects/external/assegaiphp/core/docs/package-extension-seams-plan.md).

In practice:

- `core` should provide runtime and DI seams
- packages should be able to contribute non-web entrypoints later if needed
- optional packages should not require `core` to hardcode their execution semantics

That matters for future packages such as:

- ORM maintenance tooling
- event replay or outbox tools
- queue processors
- future scheduler packages

## Proposed public UX

### Initial UX

```bash
assegai run <entrypoint>
```

Examples:

```bash
assegai run etl:scrape
assegai run etl:scrape --cinema=odeon
assegai run showtimes:refresh --source=ster-kinekor
```

### Possible later UX

```bash
assegai schedule:run
assegai worker:run
assegai task:list
```

Those are later surfaces. The first milestone should stay small.

## Example workflow

For the cinema app example:

1. `ApiModule` serves the public showtimes API
2. `WebModule` renders the web experience
3. `EtlModule` contains scraper providers and app command handlers
4. the developer runs:

```bash
assegai run etl:scrape --cinema=nu-metro
```

The CLI runtime should:

- boot the app
- prepare the module graph
- resolve the ETL command handler
- inject the scraper providers it needs
- run the task
- return a meaningful exit code

## Proposed phases

### Phase 1: runtime generalization

Deliver:

- introduce a runtime-neutral application runtime contract
- adapt existing HTTP runtimes to the broader contract
- keep current HTTP behavior unchanged

Success looks like:

- the runtime seam is no longer HTTP-only by design
- `App::run()` can support more than one runtime family cleanly

### Phase 2: explicit execution contexts

Deliver:

- introduce context-specific runtime state
- keep HTTP request state clearly isolated from non-web state
- define the first non-web execution context contract

Success looks like:

- runtime-local state is modeled by context type rather than assumed request globals

### Phase 3: CLI runtime as the first non-web target

Deliver:

- add a CLI runtime
- add `CliExecutionContext`
- add argument and option parsing for app entrypoints
- support exit codes and runtime-safe error handling

Success looks like:

- the framework can boot and run a non-web execution path without request or response objects

### Phase 4: app entrypoint discovery

Deliver:

- add app-defined command or task handler registration
- integrate discovery with modules and providers
- allow `assegai run <entrypoint>`

Success looks like:

- developers can define runnable workloads inside their Assegai app

### Phase 5: long-running and operational workflows

Deliver:

- better signal handling
- graceful shutdown for CLI jobs
- optional long-running worker mode
- stronger logging and progress reporting

Success looks like:

- ETL, import, export, and maintenance jobs feel like first-class citizens

### Phase 6: scheduler and worker surfaces

Deliver:

- optional scheduler runtime or scheduler package
- optional job orchestration surfaces
- possible package-level task registration

This should happen only after the basic runtime model is trustworthy.

## Important decisions

### 1. This should not replace queue workers

Some jobs should still be durable queue work.

The CLI runtime should complement queues, not replace them.

Good fits for the CLI runtime:

- manual ETL runs
- scheduled import or export jobs
- maintenance and repair commands
- operational workflows

Good fits for queues:

- durable background work
- retryable jobs
- asynchronous fan-out processing

### 2. This should not replace standalone scripts overnight

Some developers will still prefer standalone scripts for very small tasks.

That is fine.

The value of the integrated runtime path is that it gives an app-native option when the work belongs inside the app.

### 3. This should not overload the framework CLI

`assegai add`, `assegai update`, `assegai migration:*`, and similar commands remain framework operations.

`assegai run ...` is application execution.

That distinction should stay clear in docs and implementation.

### 4. This should not stop at CLI

CLI is where we should start, not where we should mentally stop.

The runtime architecture should leave room for:

- app commands
- scheduled tasks
- worker loops
- transport-specific runtimes later

## Testing strategy

This feature will need:

### Unit tests

- runtime resolution
- execution-context creation
- entrypoint discovery
- exit code handling
- lifecycle hook ordering

### Integration tests

- boot an app with a simple app-defined CLI entrypoint
- run it through the CLI runtime
- confirm provider injection works
- confirm failures become meaningful exit codes
- confirm bootstrap and shutdown hooks fire correctly

### End-to-end tests

- run `assegai run ...` against a fixture app
- assert stdout, stderr, and exit status

## Risks

### 1. Overcoupling to Symfony Console

The framework CLI already uses Symfony Console.

That is useful, but we should avoid making app runtime execution so tightly coupled to Symfony internals that the runtime stops feeling like part of `core`.

Recommendation:

- use Symfony Console where it helps
- keep the application runtime contract owned by Assegai

### 2. Treating every non-web workload like a command

Some workloads are commands.
Some are jobs.
Some are schedulable tasks.

The first milestone should not try to solve every non-web workload type at once.

### 3. Leaking HTTP assumptions into non-web code

If providers or helper APIs still assume `Request` is always present, this feature will expose those assumptions quickly.

That is a healthy pressure, but it means the implementation needs discipline.

## Definition of done

We should consider the first version successful when:

1. an Assegai app can declare one or more non-web entrypoints
2. the app can be run from the command line without fake HTTP request state
3. modules, providers, bootstrap hooks, and shutdown hooks work normally
4. app-defined CLI execution feels clearly separate from framework management commands
5. the architecture clearly supports future runtime contexts beyond HTTP and CLI
6. the feature is documented honestly as a broader runtime expansion, with CLI as the first delivered non-web surface

## Recommended first implementation slice

Start small:

1. generalize the runtime contract
2. add a CLI runtime and execution context
3. add one simple app entrypoint model
4. add `assegai run <entrypoint>`

That is enough to prove the model with a real use case like ETL or scraping, without prematurely designing the full scheduler and worker story.
