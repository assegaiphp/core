# Updates Advisor Plan

This document outlines the user-facing upgrade advisor we want to ship.

The inspiration is Angular's update guide:

- choose the version you are on
- choose the version you want
- see the steps that are automatic
- see the steps that are manual
- get links to the right docs and release notes

## Host names

Support both host shapes for the user-facing advisor:

- local: `update.localhost`
- local alias: `updates.localhost`
- public: `update.assegaiphp.com`
- public alias: `updates.assegaiphp.com`

The important part is consistency for users already familiar with the older singular host.

That lets us keep `update.localhost` working while still leaving room to treat the plural host as a public alias if we want to later.

## What the advisor should answer

For a given upgrade path, it should answer:

- from which version
- to which version
- which first-party packages are installed
- which steps `assegai update` will handle automatically
- which manual steps remain
- which docs and release notes the user should read

## Minimum useful version

The first useful version does not need to be fancy.

It can be a simple manifest-driven page that supports:

- `from`
- `to`
- installed packages such as:
  - `core`
  - `orm`
  - `events`
- a rendered result page with:
  - automatic steps
  - manual steps
  - warnings
  - links

## Suggested data model

Each upgrade entry should be stored as structured data.

Example fields:

- `from`
- `to`
- `packages`
- `automaticSteps`
- `manualSteps`
- `warnings`
- `releaseNotesUrl`
- `blogUrl`
- `docs`

That lets us drive both:

- the website advisor
- release-time upgrade documentation

## Release workflow tie-in

Each milestone release should produce advisor data.

That means the release checklist should include:

- release notes
- blog article
- upgrade notes
- updates advisor manifest entry
- `assegai updates:scaffold <from> <to>` when a new upgrade path opens

If we do that consistently, the advisor stays current instead of becoming a forgotten side project.

## Suggested first rollout

### Phase 1

Ship internal structure first:

- define the manifest format
- write entries for:
  - `0.7.6 -> 0.8.0`
  - `0.8.x -> 0.9.0`
- link the advisor plan from developer docs

### Phase 2

Ship a simple website page on `updates.localhost` / `updates.assegaiphp.com`:

- version selectors
- package toggles
- rendered upgrade steps

### Phase 3

Improve it with polish:

- better filtering
- package-specific warnings
- direct links into the correct guide sections
- maybe command snippets where useful

## Current immediate priority

Before the full advisor page exists, we should still make upgrades safer by improving the CLI.

That means:

- `assegai update` should replay installed package installers
- milestone releases should ship explicit upgrade notes
- `assegai updates:scaffold <from> <to>` should create the site-consumable upgrade entry and draft upgrade notes
- docs should call out the preferred upgrade command for humans

That gives us real upgrade safety now, while the advisor site is being built.
