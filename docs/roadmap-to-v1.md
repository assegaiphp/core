# Roadmap To 1.0.0

This document lays out the release targets from `0.8.0` to `1.0.0`.

It is an internal planning document for the team. It is not a public promise that every detail here will ship exactly as written, but it should be the default map we work from.

## Release philosophy

We are optimizing for correctness over speed.

That means:

- each milestone should have a clear theme
- each release should feel coherent, not random
- patch releases should be for fixes and polish
- milestone releases should move a major area of the framework forward
- every milestone release should have a matching blog article and release notes

## Version targets

## `0.8.0` OpenSwoole Runtime Foundations

Theme: give AssegaiPHP a real long-lived runtime path and make it safe enough to use as an experimental runtime target.

Primary goals:

- ship runtime abstraction as a first-class concept
- ship `serve --runtime=openswoole`
- support project-level runtime configuration
- keep request-scoped state isolated across long-lived workers
- support application bootstrap and shutdown hooks cleanly
- route runtime failures back through framework error handling
- document how to try the runtime and what its current limits are

Release bar:

- OpenSwoole startup path works through the CLI
- invalid runtime settings fail early and clearly
- runtime tests cover worker lifecycle, sequential requests, and failure recovery
- shutdown hooks behave consistently across PHP and OpenSwoole runtimes
- guides and one engineering blog article are ready

Not required for `0.8.0`:

- WebSockets
- streaming features
- task workers as a polished product surface
- production-ready claim for the runtime

Release article:

- topic: how the OpenSwoole runtime was built, why it matters, what is experimental, and where the runtime goes next

## `0.9.0` ORM Stability Rewrite

Theme: make the ORM stable, predictable, and genuinely cross-dialect instead of partially working in uneven ways.

Primary goals:

- harden MySQL support first
- fix correctness and polish issues in repositories, entity handling, relations, migrations, and schema generation
- make the query builder dialect-aware instead of assuming one SQL flavor everywhere
- implement SQLite properly as a supported development and testing target
- implement PostgreSQL properly as a supported database target
- make SQL generation context-aware so MySQL-specific and PostgreSQL-specific syntax come from the right dialect layer
- improve ORM documentation so the recommended patterns match real application code

Release bar:

- MySQL behavior is treated as the reference implementation and is cleaner and more stable than it is today
- SQLite support is good enough for real local development and tests
- PostgreSQL support is no longer a placeholder path
- ORM tests cover all supported dialects in a meaningful way
- security-sensitive query paths keep using prepared statements and validated identifiers
- one release article explains the rewrite, the dialect model, and the practical benefits for users

Release article:

- topic: why the ORM was rewritten, what changed across MySQL, SQLite, and PostgreSQL, and what developers gain from the new model

## Likely `1.0.0+` candidates

These are valuable roadmap items, but under the current `1.0.0` north star they do not automatically count as identity-defining blockers.

They are strong candidates for `1.0.0+` unless we later decide that one of them has become central enough to the framework's minimum identity that it must land first.

### Entity-Driven Database Sync

Theme: make entity files the practical source of truth for schema intent, while still preserving explicit migration history.

Primary goals:

- add `assegai database:sync` and `assegai db:sync`
- scan a project workspace or an explicit entity list
- build a desired schema snapshot from entity metadata
- diff that snapshot against the live database schema
- generate normal migration files instead of mutating the database directly by default
- support MySQL, SQLite, and PostgreSQL through the ORM's dialect-aware schema layer
- support relation-aware sync, including configurable many-to-many join-table inference
- keep the existing manual migration workflow fully supported

Release bar:

- `database:sync` can resolve a datasource and entity scope reliably
- sync can generate `up.sql` and `down.sql` migrations from entity diffs
- the generated migrations fit the existing migration directory layout
- join-table inference works predictably and is easy to opt out of
- destructive changes are surfaced clearly instead of being hidden behind magic
- one release article explains the entity-first workflow, what it automates, and what still needs explicit developer judgment

Not required for `0.10.0`:

- direct database mutation as the default sync behavior
- automatic rename detection
- fully automatic destructive-change approval
- replacing handwritten migrations

Release article:

- topic: why Assegai is making entity files the source of truth, how `database:sync` works, and how it fits alongside manual migrations

### Application Runtime Contexts And Scheduling

Theme: let Assegai apps run cleanly across non-web execution contexts, with scheduling as the first higher-level orchestration surface built on that runtime model.

Primary goals:

- generalize runtime thinking beyond HTTP-only execution
- add a first-class non-web runtime context with CLI as the first public surface
- support app-defined non-web entrypoints such as ETL, import, export, and maintenance jobs
- establish a clean execution-context model that does not rely on fake HTTP request state
- add scheduling on top of that runtime model without forcing queue workers and app commands into the same abstraction
- document where CLI jobs, schedulers, and durable queue workers each fit best

Release bar:

- the broader application runtime story is clear and not limited to HTTP assumptions
- an Assegai app can run a non-web entrypoint cleanly from the command line
- scheduled execution has a coherent design and a usable first implementation
- lifecycle hooks, provider injection, and shutdown behavior work consistently in the non-web path
- one release article explains why Assegai is expanding runtime contexts and how scheduling fits into that architecture

Not required for `0.11.0`:

- replacing durable queues
- solving every worker and scheduler pattern at once
- transport-specific runtime adapters beyond the first non-web surface
- declaring the final runtime model "done forever"

Release article:

- topic: why Assegai is expanding beyond HTTP runtimes, how the first non-web runtime works, and why scheduling belongs on top of that model instead of beside it

## The road from `0.9.x` to `1.0.0`

Theme: define the minimum agreed identity of AssegaiPHP and make that identity polished, reliable, and well documented.

The road from `0.9.x` to `1.0.0` should be about confidence, not endless feature accumulation.

There may be more milestone releases before `1.0.0`, such as `0.11.0`, `0.12.0`, or beyond. We should adapt that sequence based on framework stability and real user feedback instead of pretending we already know the exact number of remaining stops.

The key questions are not:

- "what else can we add?"
- "what can we cram in before 1.0?"

They are:

- what is the minimum agreed identity of AssegaiPHP?
- what must be true for that identity to feel polished and trustworthy?

## `1.0.0` guiding rule

`1.0.0` should be the minimum viable identity of the framework, not the maximum list of things Assegai could eventually become.

That means:

- `1.0.0` should be a polished version of what it essentially means to be AssegaiPHP
- anything that feels surplus to that identity should land in `1.0.0+`
- optional power features are welcome, but they should not hold `1.0.0` hostage unless they are central to the framework's identity

## The identity test

Use this rule of thumb:

> if removing a feature still leaves us with something that is clearly and honestly AssegaiPHP, that feature is probably not a `1.0.0` blocker

Examples of things that may be valuable without being identity-defining:

- OpenSwoole graduating from experimental
- schedulers
- WebSockets
- microservices
- transport-specific runtimes
- perfect dialect parity in every edge case
- highly automated migration or schema tooling
- every possible generator or code scaffold

Those can absolutely matter. They just do not all have to be part of the minimum identity of `1.0.0`.

The core question is:

> what must be true before we can tell people AssegaiPHP 1.0.0 is ready to build serious projects on?

### `1.0.0` definition of done

#### 1. Core framework identity is solid

The default Assegai experience should be stable and pleasant:

- module-driven application structure works predictably
- providers, dependency injection, and exports behave consistently
- controllers, routing, middleware, guards, interceptors, pipes, and exception handling feel coherent
- the default PHP runtime is stable and well tested
- the CLI can create, run, inspect, and update apps reliably

#### 2. Lifecycle and extension seams are trustworthy

- the OpenSwoole runtime is either:
  - stable enough to graduate from experimental, or
  - still explicitly marked experimental with clear boundaries
- lifecycle hooks, request scope, response emission, and package bootstrapping behave consistently
- `core` exposes the right extension seams for packages like `orm`, `events`, queues, and future packages
- package-specific attributes are not hardcoded into `core`
- lifecycle and DI extension points are clean enough to build on long-term

#### 3. The first-party data story is good enough to trust

- the ORM has a clear and honest support story for the dialects we claim to support
- common create, update, relation, and migration workflows are documented and predictable
- if entity-driven sync ships before `1.0.0`, it is either stable enough to recommend or clearly labeled with honest boundaries
- if some advanced ORM features are not yet mature, the docs and release notes say so plainly

#### 4. Documentation and upgrade quality meet the identity bar

- the website guides are beginner-friendly, technically accurate, and aligned with shipped behavior
- advanced guides are honest about what is shipped and what is still evolving
- upgrade notes exist for milestone releases where behavior changed
- release articles exist for all milestone releases
- the updates and release guidance are good enough that teams can move between milestones without guesswork

#### 5. Release discipline is part of the product, not an afterthought

- releases follow the playbook in `release-playbook.md`
- milestone scope is visible before the release starts
- patch releases are used for fixes instead of mixing them into the next milestone blindly
- release notes and blog content are prepared as part of the release, not after it

## What is not required for `1.0.0`

These are explicitly not automatic blockers for `1.0.0`:

- every optional runtime being feature-complete
- OpenSwoole graduating from experimental
- scheduling being fully realized
- microservices and transport adapters
- WebSockets and streaming
- every ORM convenience feature being fully mature
- perfect parity across every database edge case
- every code generator or automation being in place

If any of those become central enough to the framework's identity, we can revisit them. But they should not silently expand the meaning of `1.0.0` by default.

## Near-term planned milestones

This is the working near-term sequence, not a fixed promise that there will be no additional milestones before `1.0.0`.

1. `0.8.0` OpenSwoole Runtime Foundations
2. `0.8.x` patch releases for fixes and polish if needed
3. `0.9.0` ORM Stability Rewrite
4. `0.9.x` patch releases for ORM and compatibility fixes if needed
5. additional `0.x` milestones if stability, polish, or feedback say we still need them
6. `1.0.0` confidence release once the framework has actually earned it

## Current placement under the `1.0.0` filter

### Likely before `1.0.0`

- core default runtime stability
- module, provider, DI, and export behavior
- lifecycle and package extension seams
- CLI reliability for creating, serving, updating, and inspecting apps
- an honest first-party ORM story, with at least the minimum supported-dialect story documented clearly
- documentation, upgrade guidance, and release discipline

### Likely `1.0.0+`

- entity-driven database sync
- application runtime contexts beyond HTTP
- scheduling
- OpenSwoole graduating from experimental
- WebSockets
- microservices and transport adapters
- other high-level DX or runtime-expansion features that are valuable but not identity-defining

## What counts as a patch release

Use `0.x.y` patch releases for:

- bug fixes
- regressions
- docs corrections
- test coverage improvements
- small compatibility fixes
- polish that does not change the shape of the release theme

Do not use patch releases for:

- new large framework surfaces
- major behavior shifts
- milestone-sized architecture changes

Those belong in the next milestone release.

## Required release outputs

For every milestone release, we should ship all of the following:

- release tag
- release notes
- one blog article
- updated guides where relevant
- upgrade notes when behavior changed in a way users need to understand
