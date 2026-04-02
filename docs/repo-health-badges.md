# Repo Health Badges

This document explains how Assegai repositories should report their current health in their `README.md` files.

The goal is simple:

- help people see whether a repo is active and trustworthy at a glance
- keep the badge row short and consistent
- make sure the badges point at real signals, not aspirational claims

## The standard badge set

Every public Assegai repo should eventually expose the same five badges near the top of its README:

- latest release
- tests
- supported PHP version
- license
- status

That gives readers the basics they usually want first:

- is this package released
- is CI green
- what PHP version does it support
- what license is it under
- is it stable, experimental, or in a milestone rewrite

## Keep the row short

Do not turn the README header into a dashboard.

Five badges is enough for the default row.

If a repo needs more detail, put it in docs or on a status page later rather than stacking more badges into the header.

## Source of truth

The badge row should be driven by real package metadata where possible.

- release badge: GitHub releases and tags
- tests badge: GitHub Actions workflow status
- PHP badge: `composer.json` PHP constraint
- license badge: repository license
- status badge: `meta/repo-health.json`

## Repo health manifest

Each repo should carry a small manifest at:

- `meta/repo-health.json`

The current shape is:

```json
{
  "repository": "assegaiphp/core",
  "package": "assegaiphp/core",
  "php": ">=8.3",
  "license": "MIT",
  "ci": {
    "workflow": "php.yml",
    "label": "tests"
  },
  "status": {
    "label": "0.8.0 milestone",
    "color": "2563eb",
    "summary": "OpenSwoole runtime foundations and release-prep work."
  }
}
```

For now, the README badges are still written directly in Markdown, but this manifest gives us one stable place to read status from later.

## What the status badge should mean

The status badge should stay high-level.

Good examples:

- `active`
- `0.8.0 milestone`
- `0.9.0 rewrite in progress`
- `experimental`

Bad examples:

- a long feature list
- internal implementation notes
- roadmap paragraphs squeezed into a badge label

If more explanation is needed, put it in the summary field of `repo-health.json` and surface it later on a status page or updates page.

## Current rollout

Phase 1 is:

- `assegaiphp/core`
- `assegaiphp/orm`
- `assegaiphp/console`
- `assegaiphp/auth`
- `assegaiphp/events`
- `assegaiphp/rabbitmq`
- `assegaiphp/beanstalkd`

Phase 2 should extend the same pattern to:

- `assegaiphp/common`
- `assegaiphp/util`
- `assegaiphp/validation`
- `assegaiphp/forms`
- `assegaiphp/collections`


## CI requirement

If a repo has a tests badge, it should have a real tests workflow behind it.

That sounds obvious, but it is important.

We should not add a green tests badge that points at a workflow which does not exist or does not actually exercise the supported runtime path.

So before adding badges to a repo:

1. confirm there is a real workflow
2. confirm it runs on `main`
3. confirm it covers the repo's meaningful default test lane

## Release workflow expectation

When a release milestone changes the repo's public state, update:

1. `meta/repo-health.json`
2. the README status badge if needed
3. any linked release notes or upgrade guidance

Examples:

- `core` moving toward `0.8.0`
- `orm` moving toward `0.9.0`
- a package moving from `experimental` to `active`

## Future automation

The end state should be:

- every repo has `meta/repo-health.json`
- the README badge row follows the same pattern everywhere
- a lightweight script or release action can validate that the badge row and health manifest stay in sync
- the website can later consume these manifests for a broader package-health or release-health view
