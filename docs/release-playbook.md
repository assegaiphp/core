# Release Playbook

This document explains how we should organize AssegaiPHP releases.

The goal is to keep the process simple, predictable, and light enough for a small team.

## The problem we are solving

Right now the default pattern has mostly been:

- open PRs
- merge them when they are ready
- figure out the release shape later

That works for early momentum, but it gets harder once the framework starts shipping milestone-sized work.

Without a release process, we end up with a few risks:

- releases feel random instead of coherent
- feature work and stabilization work compete with each other
- docs and blog posts get written too late
- it is harder to know what belongs in the next release
- patch fixes can get mixed into unfinished milestone work

This playbook keeps the process simple while giving us more control.

## The simple strategy

Use a milestone-driven trunk workflow.

That means:

- day-to-day work still happens through normal feature branches and PRs
- `main` is the active development branch
- each PR should belong to a milestone
- when a milestone is feature-complete, create a short-lived release branch
- stabilize the release branch
- tag the release from the release branch
- merge or cherry-pick any release-only fixes back to `main`

This is simpler than keeping long-lived development branches, but more disciplined than merging everything and hoping the release shape appears by itself.

## Branch strategy

### Normal development

- branch from `main`
- use small focused feature branches
- merge into `main` through PRs

Recommended branch examples:

- `feature/openswoole-runtime-config`
- `fix/mysql-schema-quoting`
- `docs/orm-enum-guide`

### Release stabilization

When the current milestone is feature-complete, create a release branch:

- `release/0.8.0`
- `release/0.9.0`

That branch is only for:

- bug fixes
- release notes
- blog article preparation
- docs needed for the release
- test and CI stabilization

Do not merge new unrelated feature work into the release branch.

`main` should immediately become the place for the next milestone.

## Milestones and labels

Keep this lightweight.

At minimum, every PR should have:

- one milestone
- one type label

Recommended milestone labels:

- `0.8.0`
- `0.9.0`
- `1.0.0`

Recommended type labels:

- `feature`
- `bug`
- `docs`
- `test`
- `refactor`
- `breaking`

Optional but useful:

- `release-note`
- `needs-docs`
- `needs-upgrade-note`

The important rule is simple:

> if a PR does not clearly belong to a milestone, it should not be merged until we know where it belongs

## Milestone workflow

### 1. Define the milestone before the work spreads

For each milestone, write down:

- the theme
- the must-have targets
- the things that are explicitly out of scope
- the blog article topic

For the current roadmap:

- `0.8.0`: OpenSwoole Runtime Foundations
- `0.9.0`: ORM Stability Rewrite
- `1.0.0`: confidence and stability release

### 2. Merge feature work into `main`

During active development:

- merge milestone-aligned PRs into `main`
- keep PRs small enough to review and revert if needed
- prefer finishing slices completely instead of starting too many parallel half-features

### 3. Start release freeze when the milestone is feature-complete

Create the release branch when:

- the must-have work is merged
- only polish, stabilization, docs, and release packaging remain

At that point:

- branch `release/x.y.z` from `main`
- move any remaining non-release work to the next milestone

### 4. Stabilize the release branch

Only allow:

- bug fixes
- regression fixes
- docs fixes
- CI fixes
- release notes
- release blog post
- upgrade notes

If a fix lands on the release branch first, merge or cherry-pick it back to `main`.

### 5. Tag and publish

Before tagging, make sure the following are done:

- tests for the release theme are green
- release notes are written
- blog article is ready
- docs are updated
- upgrade notes are written if needed
- version number and tag are agreed

Then:

- tag the release
- publish release notes
- publish the blog article

## Patch release workflow

Patch releases should be boring.

Use patch releases like `0.8.1` or `0.9.2` for:

- regressions
- important bug fixes
- compatibility fixes
- documentation corrections

Do not turn a patch release into a stealth milestone.

If the work feels like a new headline, it belongs in the next milestone release.

Recommended patch flow:

1. branch from the last release tag or release branch
2. merge only the fixes needed for the patch
3. tag the patch release
4. merge or cherry-pick the fixes back to `main`

## Release checklist

Use this checklist for each milestone release.

### Before release freeze

- milestone scope is written down
- milestone PRs are assigned correctly
- unfinished work is moved out of the milestone if needed

### During release freeze

- release branch created
- regression fixes only
- docs updated
- release notes drafted
- blog article drafted
- upgrade notes drafted when needed

### Before tagging

- tests are green
- package-specific checks are green
- release notes are final
- blog article is ready to publish
- docs reflect shipped behavior, not aspirational behavior

### After tagging

- publish release notes
- publish the blog article
- announce the release
- merge or cherry-pick release-only fixes back to `main`
- create or confirm the next milestone

## Blog article expectations

Every milestone release should have one release article.

Keep the article practical.

A good release article should explain:

- what problem this milestone tackled
- what changed for users
- what is now possible
- what is still experimental or still coming later
- what the next milestone is about

Planned milestone articles:

- `0.8.0`: OpenSwoole runtime foundations
- `0.9.0`: ORM stability rewrite
- `1.0.0`: what AssegaiPHP 1.0 means and why the framework is ready

## What not to do

Avoid these patterns:

- merging milestone-sized work without assigning it to a milestone
- mixing unrelated features into a release branch
- writing docs and release articles after the release is already out
- using patch releases to smuggle in major new surfaces
- waiting until the end to decide what the release is really about

## Team rule of thumb

If there is any doubt, use this simple rule:

> `main` is for building the next milestone, and `release/x.y.z` is for making one milestone safe to publish.

That one distinction should keep the process understandable for the whole team.
