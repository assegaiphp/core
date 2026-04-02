# Test Suite Stabilization Plan

## Why we need this

We want to be able to say, with confidence, that a release does not introduce regressions.

Right now the test story is uneven:
- `console` already uses Pest and is the easiest package to run confidently.
- `core` uses Codeception, but its `Unit` suite is mostly plain assertion-based tests with no strong dependency on Codeception-specific modules.
- `orm` uses Codeception too, but its `Unit` suite is coupled to the global `Db` module, which means focused tests can fail before they even start if the local MySQL connection is not available.

That last point is the most urgent problem. It turns simple regression work into environment debugging.

## Current state

### `console`
- Test runner: Pest / PHPUnit
- Current state: healthy enough to use as the reference package for test ergonomics

### `core`
- Test runner: Codeception `Unit`
- Current state: workable, but mostly acting like a PHPUnit-style unit suite anyway
- Main issue: inconsistency with `console`, not outright breakage

### `orm`
- Test runner: Codeception `Unit` plus `SQLite`
- Current state: the least stable package for regression testing
- Main issue: `tests/Unit.suite.yml` enables the `Db` module globally against MySQL, so even pure unit tests can fail before the test itself runs

## Recommendation

Use a hybrid strategy:
- Pest / PHPUnit becomes the default for unit tests and package-level integration tests.
- Codeception is kept only where it provides real value, such as browser-style acceptance flows or specialized modules we actively want.

In simple terms:
- unit tests should not need a database server unless the test is explicitly a database integration test
- integration tests should be grouped by dialect or runtime
- release confidence should come from a small number of predictable suites, not from one giant suite with hidden environment assumptions

## Target structure

### 1. Fast unit tests
Use Pest / PHPUnit.

These should cover:
- pure helpers
- SQL generation
- metadata compilation
- schema SQL generation
- result object behavior
- migration ordering logic
- runtime helpers

Rule:
- no live MySQL, PostgreSQL, or SQLite server required unless the test is explicitly marked as integration

### 2. Fast local integration tests
Use Pest / PHPUnit too.

These should cover:
- SQLite-backed repository and schema flows
- API docs generation
- runtime request lifecycle checks
- queue or events package behavior that can run in-process

Rule:
- should run on a normal machine with only local PHP extensions installed

### 3. Dialect integration tests
Run separately for:
- MySQL
- PostgreSQL
- SQLite

These should cover:
- connection creation
- schema creation / alter / truncate / drop
- save / update / soft delete / restore
- upsert behavior
- migration run / revert / redo
- dialect-specific SQL features

Rule:
- these are allowed to require services
- they should not be part of the default quick unit command

### 4. Acceptance or system tests
Keep Codeception only if we actually need it.

Examples:
- browser-level docs flow
- CLI interactions that are easier to express as command/system tests
- future end-to-end runtime tests if Pest alone becomes awkward there

If Codeception is not adding real value, do not keep it just because it was there first.

## Package-by-package plan

## `0.8.x` release line

### Goal
Stabilize the release process and remove the biggest test pain points.

### Work
1. Stop treating `orm` unit tests as MySQL integration tests.
2. Remove the global `Db` module dependency from `orm/tests/Unit.suite.yml`.
3. Move the new focused ORM SQL/helper tests onto Pest or PHPUnit first.
4. Keep `console` as the reference example for how simple test execution should feel.
5. Define release gates for `0.8.0`:
   - `console` test suite green
   - `core` focused runtime/api docs tests green
   - ORM fast smoke checks green

### Expected outcome
We get immediate relief from the “test won’t even start” problem.

## `0.9.0` ORM milestone

### Goal
Make ORM regressions obvious and reproducible.

### Work
1. Move ORM unit tests to Pest / PHPUnit.
2. Keep SQLite integration as a first-class fast suite.
3. Create dedicated MySQL integration tests that are not mixed into unit runs.
4. Add a PostgreSQL integration suite only after the dialect work starts to land.
5. Make the ORM package expose clear commands such as:
   - fast unit tests
   - SQLite integration tests
   - MySQL integration tests
   - PostgreSQL integration tests

### Expected outcome
We can change the ORM and know which layer broke:
- unit logic
- SQLite behavior
- MySQL behavior
- PostgreSQL behavior

## `1.0.0` road to confidence

### Goal
Every major package should have a predictable regression story.

### Work
1. `core` unit tests move to Pest / PHPUnit unless a specific Codeception feature is still needed.
2. runtime and OpenSwoole coverage gets an explicit integration layer.
3. release branches must pass the same small set of gates every time.
4. dialect integration must run in CI, not only on one laptop.

### Expected outcome
Release confidence stops depending on memory and manual spot checks.

## Specific ORM fix we should do first

The first test-stack cleanup should be this:
- remove the `Db` module from `orm/tests/Unit.suite.yml`
- move database-dependent tests into explicit integration suites

Why:
- it is the source of the current false failures
- it blocks focused regression work
- it makes the package feel less stable than it really is

## Proposed tooling choice

### Pest / PHPUnit for most tests
Why:
- simpler test bootstrap
- focused test execution is easier
- good fit for the existing `console` package
- better for pure unit and package-level integration tests

### Codeception only where justified
Keep it only for:
- acceptance/system flows
- any test that genuinely benefits from its modules

Do not keep Codeception as the default unit runner if it is mostly being used as an assertion wrapper.

## CI strategy

We should split CI into clear layers.

### Layer 1: fast checks on every PR
- static analysis
- unit tests
- SQLite integration tests

### Layer 2: dialect matrix
- MySQL integration
- PostgreSQL integration

### Layer 3: release verification
- run everything required for the milestone
- include migration flows and export/runtime smoke tests where relevant

## Local developer commands we should aim for

Each package should eventually expose simple commands like:
- `composer test`
- `composer test:unit`
- `composer test:sqlite`
- `composer test:mysql`
- `composer test:pgsql`

Not every package needs every command, but the pattern should be predictable.

## Release gates

For a release branch, the minimum rule should be:
- no package ships with known failing default tests
- milestone-critical integration suites must pass
- release notes should list any still-experimental surfaces honestly

For `0.8.0` specifically:
- `console` tests green
- `core` runtime-focused tests green
- ORM fast smoke and SQL helper coverage green
- any missing full MySQL/PostgreSQL integration should be stated honestly in the notes

For `0.9.0` specifically:
- ORM unit tests green
- SQLite integration green
- MySQL integration green
- PostgreSQL integration either green or explicitly not part of the milestone yet

## Practical next steps

1. Move ORM unit tests off the global Codeception `Db` module.
2. Introduce Pest to `orm` alongside the existing setup.
3. Start by porting the new focused tests first:
   - connection config
   - database manager SQL
   - schema SQL
   - migrations list ordering
4. Once that works, port the remaining pure unit tests in small batches.
5. After ORM is stable, make the same decision for `core`.

## Simple rule for the team

When writing a new test, ask this first:
- Does this need a live database or service?

If the answer is no:
- it belongs in Pest / PHPUnit unit tests

If the answer is yes:
- it belongs in an explicit integration suite for that dialect or service

That one rule will remove a lot of ambiguity.
