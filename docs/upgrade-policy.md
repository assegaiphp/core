# Upgrade Policy

This document explains how we should help existing AssegaiPHP applications move forward without surprise breakage.

The simple rule is:

> old apps should usually keep working first, and only then be guided toward the new preferred shape

## What that means in practice

When we improve the framework, we should prefer this order:

1. add the new official path
2. keep a compatibility path where practical
3. teach `assegai update` how to repair the project automatically
4. write upgrade notes for anything users need to understand
5. remove old behavior only after a clear migration window

That keeps the framework moving without turning upgrades into guesswork.

## Upgrade expectations by release type

### Patch releases

Patch releases should be safe and boring.

Use them for:

- bug fixes
- compatibility fixes
- docs corrections
- small polish

Patch releases should not require users to rethink their project structure.

### Milestone releases

Milestone releases can introduce new preferred patterns, but they must ship with:

- release notes
- upgrade notes when behavior changed
- `assegai update` support where the change can be applied automatically
- a clear statement of what is automatic and what is manual

## The role of `assegai update`

`assegai update` is the human-friendly upgrade command.

Its job is to make upgrades safer by:

- hydrating `assegai.json`
- hydrating `composer.json`
- upgrading the relevant first-party packages
- replaying installed package installers after the Composer update
- refreshing the project wiring that older apps may be missing

That last point matters for package-driven integration.

For example:

- an older ORM app may have `assegaiphp/orm` installed but not yet import `OrmModule`
- an older events app may have `assegaiphp/events` installed but not yet import `EventsModule`

The update flow should repair that wiring automatically when the package installer is idempotent.

## Compatibility shims

We should keep compatibility shims when they buy users a smoother upgrade path at low complexity cost.

Examples:

- older bootstrap patterns can keep working while docs move to `AssegaiFactory::createFromProject(...)`
- `InjectRepository` can keep a compatibility fallback while `OrmModule` becomes the official bridge path

Compatibility shims should not live forever, but they are valuable during milestone transitions.

## What must be documented every time behavior shifts

When we change the preferred way to wire or use part of the framework, we should answer:

- what is the new preferred way
- what still works for older apps
- whether `assegai update` handles it automatically
- what users still need to change by hand
- when we expect the old path to stop being supported, if known

## Upgrade note checklist

For each milestone release, ask:

1. Did package wiring change?
2. Did runtime/bootstrap behavior change?
3. Did config defaults change?
4. Did CLI commands move or change ownership?
5. Did docs change the recommended pattern?

If the answer is yes to any of these, write an upgrade note.

## Current direction

For the current package architecture work, the intended upgrade story is:

- `assegai add <package>` remains the normal human workflow
- `composer require ...` remains the low-level/manual path
- `assegai update` repairs missing package integrations for already-installed packages
- release notes explain any new preferred module wiring, runtime wiring, or config defaults

That gives existing apps a path forward without making developers remember fragile extra steps.
