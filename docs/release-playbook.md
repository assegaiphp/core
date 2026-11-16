# Release Playbook

This document explains how we should organize AssegaiPHP releases.

Every framework release-train module shares the same major and minor (`A.B` in
`A.B.X`); patch versions may differ. Preserve and coordinate the pre-existing
streams through the reviewed version matrix. Coordinate every module and the
AssegaiPHP Website documentation protocol before a major/minor release. See
[Versioning Strategy](./versioning-strategy.md) for the mandatory inventory and
readiness checks. Missing module or Website readiness blocks the release.

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

Use the project's milestone workflow.

That means:

- local working branches are allowed; remote working branches require the repository owner's
  explicit permission for the repository, branch and actions
- repositories with an existing `develop` accept work there before a PR to `main`
- each PR should belong to a milestone
- the release maintainer creates release branches from remotely merged `main` and owns tags/releases
- preserve branch protection and all applicable review/CI gates

This playbook describes the maintainer workflow. Publication and remote branch
creation still require the repository owner's explicit authorization.

## Branch strategy

### Normal development

- preserve the repository's existing workflow and user work
- where `develop` exists, integrate accepted local work into local `develop`
- obtain scoped authorization before pushing existing `develop`, then open its
  PR into `main`; never push directly to `main`
- if protection requires a PR into `develop`, stop for an explicitly authorized
  compliant path; do not bypass protection or infer a remote-branch exception
- pull local `main` and synchronize `develop` only after the remote merge
- do not create `develop` in a repository that lacks it

### Release stabilization

When the coordinated milestone is ready, the release maintainer creates a release branch:

- `release/0.8.0`
- `release/0.9.0`
- `release/0.10.0`

That branch is only for:

- bug fixes
- release notes
- blog article preparation
- docs needed for the release
- test and CI stabilization

Do not merge new unrelated feature work into the release branch.

Subsequent work follows the repository's existing authorized workflow.

## Milestones and labels

Keep this lightweight.

At minimum, every PR should have:

- one milestone
- one type label

Recommended milestone names:

- `0.8.0`
- `0.9.0`
- `0.10.0`
- `0.11.0`
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

### Assess requests before accepting a new baseline

For an integration request:

- reproduce the blocker on the released versions and inspect supported contracts
- exhaust sound compatible solutions before requesting an upstream change
- identify the smallest necessary change and its compatibility/version impact
- explain the full release effort to the requesting owner before proposing a bump:
  module/legacy-stream coordination, constraints and locks, verification and CI,
  Website versioned docs/archives, upgrade guidance, advisor entry and article
- involve Core, Console, Website and affected module owners at intake
- distinguish local implementation evidence from coordinated release readiness

Public release material explains shipped behavior, compatibility and upgrade
steps for users.

### 1. Define the milestone before the work spreads

For each milestone, write down:

- the theme
- the must-have targets
- the things that are explicitly out of scope
- the blog article topic

For the current near-term roadmap:

- `0.8.0`: OpenSwoole Runtime Foundations
- `0.9.0`: ORM Stability Rewrite
- `0.10.0`: Configurable Authentication Failure Handling and Complete Exception-Filter Lifecycle
- additional `0.x` milestones as needed based on stability and feedback
- `1.0.0`: confidence and stability release when the framework is actually ready

### 2. Integrate milestone work through the repository workflow

During active development:

- where `develop` exists, integrate accepted local work there, then use the
  authorized PR path into `main`
- keep PRs small enough to review and revert if needed
- prefer finishing slices completely instead of starting too many parallel half-features

### 3. Start release freeze when the milestone is feature-complete

The release maintainer creates the release branch when:

- the must-have work is merged
- only polish, stabilization, docs, and release packaging remain
- every module's shared release line and Website preparation are accounted for

At that point:

- the release maintainer branches `release/x.y.z` from remotely merged `main`
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

Coordinate release fixes with the release maintainer and repository owner.
Integrate them through the authorized workflow; do not push directly to `main`.

### 5. Tag and publish

Before tagging any module in a major/minor release, make sure these are done:

- tests for the release theme are green
- release notes are written
- blog article is ready
- docs are updated
- upgrade notes are written if needed
- updates advisor entry is prepared if the release changes user-facing behavior
- version number and tag are agreed
- every module matches its reviewed framework/legacy version stream and planned constraints
  and an owner-confirmed readiness result, including unchanged modules
- dependency requirements admit the planned graph while retaining separately
  reviewed compatibility where supported; a module's aligned release number
  does not establish an API reason to remove an older dependency line
- compatibility reviews distinguish their supported intervals from executed
  artifact samples, and current-train qualification verifies the actual target
  graph rather than accepting an ordinary compatibility observation
- the AssegaiPHP Website owner has verified the versioned docs, release article,
  upgrade path and updates-advisor preparation under Website's protocol

Under the separately authorized publishing plan:

- the release maintainer tags and releases modules in dependency order
- publish release notes
- publish the blog article
- publish the upgrade notes and updates advisor entry when needed
- update Website's Composer lock after the required package releases, verify the
  actual application and deploy Website from `main` last

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

1. agree the patch scope with the release maintainer and repository owner
2. integrate only the required fixes through the existing authorized workflow
3. the release maintainer creates and publishes the patch tag/release
4. coordinate any release-only fixes back through the repository's PR workflow

## Release checklist

Use this checklist for each milestone release.

### Before release freeze

- milestone scope is written down
- milestone PRs are assigned correctly
- unfinished work is moved out of the milestone if needed
- the complete module inventory and shared `A.B` are agreed with all owners
- the Website owner is included in release preparation

### During release freeze

- the release maintainer has created the release branch
- regression fixes only
- docs updated
- release notes drafted
- blog article drafted
- repo health manifest and README status badge reviewed for the milestone
- run `assegai updates:scaffold <from> <to>` when a new upgrade path opens
- upgrade notes drafted when needed
- updates advisor entry drafted when needed

### Before tagging

- tests are green
- package-specific checks are green
- release notes are final
- blog article is ready to publish
- docs reflect shipped behavior, not aspirational behavior
- package `meta/repo-health.json` files reflect the current release state
- README badge rows still point at live release and CI signals
- all module readiness results and Website documentation requirements are complete

### After tagging

- publish release notes
- publish the blog article
- publish or update the updates advisor entry
- announce the release
- return release-only fixes through the repository's authorized workflow
- verify required packages before updating Website's Composer lock and deploy Website last
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

## The `1.0.0` rule

When we talk about `1.0.0`, use this framing:

- `1.0.0` is the minimum viable identity of AssegaiPHP
- it is not the complete list of everything Assegai could eventually become
- surplus or optional power features should land in `1.0.0+` unless they are clearly part of the framework's essential identity

Simple test:

> if removing a feature still leaves something that is clearly AssegaiPHP, that feature is probably not a `1.0.0` blocker

That rule should keep us from accidentally turning `1.0.0` into a permanently moving target.

## Versioning and reporting

Before each milestone release, confirm the versioning story is coherent:

- `assegaiphp/core` is the framework version users should think of as the Assegai version
- `assegaiphp/console` is the CLI version
- every framework module must share the same major and minor; mapped legacy
  streams remain coordinated and patch versions may differ. No new implicit
  exceptions are allowed for small or unchanged modules
- `assegai version` and `assegai info` should report the running CLI version and the target app's Assegai version correctly

See [Versioning Strategy](./versioning-strategy.md) for the detailed policy.

Planned milestone articles:

- `0.8.0`: OpenSwoole runtime foundations
- `0.9.0`: ORM stability rewrite
- `0.10.0`: configurable authentication failure handling and the complete exception-filter lifecycle
- likely `1.0.0+`: entity-driven database sync and the entity-first migration workflow
- likely `1.0.0+`: application runtime contexts, non-web execution, and scheduling
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

> A major/minor release requires every module and Website documentation to be
> ready together. Follow the repository's authorized branch workflow; the release
> maintainer owns release branches, tags and releases.

That one distinction should keep the process understandable for the whole team.
