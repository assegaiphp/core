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

## Delta From `0.9.0` To `1.0.0`

Theme: stop thinking in terms of "big feature pushes" and finish the stability, consistency, and release discipline work needed for a real `1.0.0`.

The gap from `0.9.0` to `1.0.0` should be about confidence.

The key question is not "what else can we add?"

It is:

> what must be true before we can tell people AssegaiPHP 1.0.0 is ready to build serious projects on?

### `1.0.0` success criteria

#### 1. Core platform stability

- the default PHP runtime is stable and well tested
- the OpenSwoole runtime is either:
  - stable enough to graduate from experimental, or
  - still explicitly marked experimental with clear boundaries
- lifecycle hooks, request scope, response emission, and package bootstrapping behave consistently

#### 2. ORM confidence

- MySQL, SQLite, and PostgreSQL all have a clear support story
- the query builder and schema generation are dialect-aware by design
- common create, update, relation, and migration workflows are documented and predictable

#### 3. Package extension seams

- `core` exposes the right extension seams for packages like `orm`, `events`, queues, and future packages
- package-specific attributes are not hardcoded into `core`
- lifecycle and DI extension points are clean enough to build on long-term

#### 4. Documentation and upgrade quality

- the website guides are beginner-friendly and technically accurate
- advanced guides are honest about what is shipped and what is still evolving
- upgrade notes exist for milestone releases where behavior changed
- release articles exist for all milestone releases

#### 5. Release discipline

- releases follow the playbook in `release-playbook.md`
- milestone scope is visible before the release starts
- patch releases are used for fixes instead of mixing them into the next milestone blindly
- release notes and blog content are prepared as part of the release, not after it

## Expected milestone sequence

This is the working sequence unless we deliberately change it.

1. `0.8.0` OpenSwoole Runtime Foundations
2. `0.8.x` patch releases for fixes and polish if needed
3. `0.9.0` ORM Stability Rewrite
4. `0.9.x` patch releases for ORM and compatibility fixes if needed
5. `1.0.0` confidence release built on stabilized core, ORM, docs, and release process

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
