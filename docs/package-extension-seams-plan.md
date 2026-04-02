# Package Extension Seams Plan

This document is an internal architecture note for future work.

One important part of this plan has now started landing:

- constructor parameter attributes can provide their own dependency value through a generic `resolveParameterValue()` seam
- `core` no longer hardcodes `#[InjectRepository]`
- queue injection is now using the same seam too
- `core` now also exposes `ParameterResolverInterface`
- modules can now configure injector extensions before provider resolution begins
- `OrmModule` uses that module-level seam to register repository resolution without teaching `core` about ORM classes

So this document is no longer purely aspirational. It now describes a direction that has already begun in the injector.

It describes how Assegai should support optional packages such as `events`, `orm`, queues, and later integrations without teaching `core` about each package's attributes or constructor tricks.

## Why this matters

Today, some package behavior leaks into `core`.

The clearest example is constructor parameter resolution:

- `orm` currently depends on `core` knowing about `#[InjectRepository]`
- queue support currently depends on `core` knowing about `#[InjectQueue]`

That works in the short term, but it creates a scaling problem:

- every new package needs new package-specific code in `core`
- `core` becomes harder to reason about
- package features become harder to use outside Assegai
- optional packages start to feel mandatory in framework internals

The design goal is to invert that relationship.

`core` should provide generic extension seams.
Packages should plug into those seams.

## Desired end state

The long-term shape should be:

- `core` owns lifecycle contracts
- `core` owns generic DI extension points
- packages own their own attributes, bootstrappers, and resolvers
- `core` does not need hardcoded knowledge of package attributes such as `OnEvent`, `InjectRepository`, or future package-specific decorators

This keeps the framework extensible without turning `core` into a registry of special cases.

## Design principles

### 1. `core` should know framework concepts, not package concepts

It is fine for `core` to know about:

- application bootstrap
- module initialization
- request scope
- parameter resolution
- provider lifecycle

It should not need to know about:

- `assegaiphp/events` listener attributes
- `assegaiphp/orm` repository injection attributes
- queue-package-specific decorators

### 2. Optional packages should stay optional

An app that does not use `events` should not pay a mental or runtime cost for event-specific code in `core`.

An app that does not use `orm` should not rely on repository-specific branches living inside the injector.

### 3. Standalone packages should stay standalone where possible

If a package can work in plain PHP, its public API should not depend on Assegai internals.

It can still ship an optional Assegai bridge namespace for integration.

That is the model `events` should follow.

## Extension seam 1: generic lifecycle hooks

The first seam is application lifecycle.

Packages need a clean place to perform setup after the app graph is ready.

Examples:

- `events` wants to auto-register `#[OnEvent(...)]` listeners
- future packages may want to preload metadata or perform application-scoped registration

### `core` should provide

- `OnModuleInitInterface`
- `OnApplicationBootstrapInterface`
- optionally later `OnApplicationShutdownInterface`

### `core` should invoke them

At a high level:

1. build the application graph
2. resolve application-scoped providers
3. run bootstrap hooks once
4. begin request handling

### Packages should use them

For example:

- `events` can ship an `EventListenerRegistrar implements OnApplicationBootstrapInterface`
- `orm` could ship a bootstrapper if it ever needs package-level startup work

`core` then only sees a provider with a lifecycle interface, not an events-specific bootstrapper.

## Extension seam 2: parameter resolution pipeline

The second seam is the injector.

This is the bigger architectural fix because it solves the current `orm` problem and prevents the same issue from spreading.

### Current problem

The injector currently contains hardcoded branches for package-specific attributes.

That means adding a new package feature usually requires changing `core/src/Injector.php`.

### What has now landed

The first version of this seam is simpler than the full resolver pipeline described below.

Today, the injector will honor any parameter attribute that exposes:

```php
public function resolveParameterValue(): mixed
```

That gives packages a lightweight escape hatch immediately, without forcing `core` to import package-specific attribute
classes.

This is enough for:

- `orm` repository injection
- queue injection
- future package attributes that can resolve themselves directly

The next layer has now landed too:

- packages can register richer resolvers through `ParameterResolverInterface`
- modules can opt into `ConfiguresInjectorInterface` when they need to register those resolvers before providers are hydrated

### Desired direction

Introduce a richer generic parameter resolution extension pipeline.

Suggested shape:

- `ParameterResolverInterface`
- `ParameterResolutionContext`
- a registry or list of active resolvers

### Proposed contract

The resolver contract should answer two questions:

1. can this resolver handle the parameter?
2. if yes, what value should be injected?

For example:

```php
interface ParameterResolverInterface
{
  public function supports(ReflectionParameter $parameter, ParameterResolutionContext $context): bool;

  public function resolve(ReflectionParameter $parameter, ParameterResolutionContext $context): mixed;
}
```

The context would carry things like:

- active injector
- active app
- current request scope
- declaring class and constructor metadata

### How packages would use it

- `orm` ships a resolver for `#[InjectRepository]`
- queue support ships a resolver for `#[InjectQueue]`
- future packages can ship resolvers for their own attributes

`core` only loops through registered resolvers. It does not need to recognize each package decorator by name.

### How those resolvers are registered early enough

Resolvers are only useful if they are present before default-scoped providers are built.

That is why `core` now also supports a module-level injector configuration seam. A package bridge module can implement
`ConfiguresInjectorInterface` and register its resolvers immediately after module discovery, before provider
resolution starts.

That is the pattern `OrmModule` now uses.

## Extension seam 3: module-level bridge providers

Some packages need more than parameter injection. They need module-level integration.

Examples:

- `events` wants an emitter plus a listener registrar
- future transports may want bridge services or adapters

The clean pattern is:

- the package stays usable standalone
- the package also provides an optional Assegai bridge namespace
- the app opts in by importing the package's module

For example:

- `Assegai\Events\EventEmitter` stays framework-neutral
- `Assegai\Events\Assegai\EventsModule` provides the Assegai-specific bridge

That is much cleaner than making `core` depend on `events`.

## Extension seam 4: package discovery and registration

If `core` provides generic lifecycle hooks and generic parameter resolvers, there still needs to be a way for packages to register their extensions.

There are two realistic models:

### Explicit registration

The package module registers its resolvers/providers itself.

This is simple and predictable.

Example:

- importing `EventsModule` registers an `EventListenerRegistrar`
- importing `OrmModule` or configuring the ORM data source registers repository resolvers

### Composer-driven auto-discovery

Composer metadata could advertise extension classes.

This is powerful, but it is also more magical and can be harder to debug.

### Recommendation

Prefer explicit module-based registration first.

That keeps package behavior visible in user code and avoids hidden bootstrapping.

Auto-discovery can be explored later if it solves a clear problem.

In the meantime, the generic attribute seam is the bridge between the old hardcoded world and the fuller resolver
pipeline described here.

## How this solves `events`

`events` should be split conceptually into two layers:

### Standalone layer

- `EventEmitter`
- `OnEvent`
- `ReflectiveListenerProvider`
- readiness watcher

No `core` dependency required.

### Assegai bridge layer

- `EventsModule`
- `AssegaiEventEmitter`
- `EventListenerRegistrar`

This bridge depends on:

- `OnApplicationBootstrapInterface`
- application-scoped provider discovery

So the only `core` work needed is the generic lifecycle seam, not package-specific event handling.

## How this solves `orm`

`orm` should stop depending on `core` hardcoding `InjectRepository`.

Instead:

- `orm` owns `#[InjectRepository]`
- `orm` ships a repository parameter resolver
- that resolver is registered through the package bridge

Then `core` no longer needs to know what a repository is.

That is a healthier boundary because:

- `orm` owns ORM concerns
- `core` owns DI orchestration

## Migration plan

The clean migration path is incremental.

### Phase 1: add lifecycle contracts

Add to `core`:

- `OnModuleInitInterface`
- `OnApplicationBootstrapInterface`

Update `App` so these hooks run at the correct points once the application graph is ready.

### Phase 2: add generic parameter resolvers

Add to `core`:

- `ParameterResolverInterface`
- `ParameterResolutionContext`
- injector support for registered resolvers

Keep existing hardcoded branches temporarily so current apps do not break.

### Phase 3: move package logic out of `core`

Move:

- `InjectRepository` handling into `orm`
- `InjectQueue` handling into queue support

This should happen behind compatibility shims first.

### Phase 4: deprecate package-specific branches in `core`

Once package resolvers are stable:

- mark hardcoded injector branches as deprecated
- remove them in a later minor or major release

### Phase 5: standardize future packages on the same pattern

New packages such as:

- `events`
- future caching packages
- microservice transports
- other infra adapters

should all use the same bridge-and-resolver model from the start.

## Things to avoid

- do not add package-specific attributes directly to `core`
- do not make `core` require every first-party package
- do not hide important package bootstrapping behind too much magic
- do not make standalone packages depend on Assegai just to offer an optional bridge

## Recommended next concrete step

The best next implementation step is:

1. add `OnApplicationBootstrapInterface` and wire it into `core`
2. use that seam for `events`
3. then design and implement `ParameterResolverInterface`
4. migrate `orm` and queue injection onto the resolver pipeline

That order keeps the first win small and useful while moving the bigger architectural problem in the right direction.
