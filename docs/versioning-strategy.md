# Versioning Strategy

This document explains how we should talk about versions across AssegaiPHP.

The main goal is simple:

- users should have one clear answer to "what version of Assegai am I on?"
- every framework release-train module shares the same major and minor line,
  with pre-existing version streams coordinated through the reviewed matrix
- the CLI should report version information in a way that matches reality

## The problem

Right now each repository has its own package version.

That is normal at the Composer package level, but it creates two kinds of confusion:

- users often mean the framework version when they say "Assegai version"
- maintainers can end up with package versions that drift away from the release milestone story

We need one shared language.

## The shared language

Use these terms consistently.

### Assegai version

When we say "Assegai version", we mean the installed version of:

- `assegaiphp/core`

That is the framework version for an app.

### CLI version

When we say "CLI version", we mean the version of:

- `assegaiphp/console`

This is the tool the developer is currently running.

### Package version

When we say "package version", we mean the version of an individual Composer package such as:

- `assegaiphp/orm`
- `assegaiphp/events`
- `assegaiphp/auth`
- `assegaiphp/common`

### Release line

When we say "release line", we mean the milestone line we are shipping across the framework, such as:

- `0.8.x`
- `0.9.x`
- `1.0.x`

## The rule for users

For user-facing communication:

- the framework version is the `assegaiphp/core` version
- the CLI version is the `assegaiphp/console` version
- the CLI should report both when it can

That means:

- `assegai --version` should report the running CLI version
- `assegai version` should report the running CLI version and the target app's Assegai version
- `assegai info` should report both too when it is pointed at a valid app workspace

## The rule for milestone releases

Framework modules, Console and Starter share the same `A.B` in `A.B.X`.
Changing either `A` or `B` requires a coordinated release across the complete
release train, including modules with no runtime changes. Patch versions may differ.

Preserve the established pre-framework version streams. RabbitMQ and Beanstalkd
were already released at `1.x`; their own minor advances with the framework
milestone. Attributes remains the existing compatible stable dependency. Their
numbers do not move Assegai itself to `1.x`. These relationships are explicit
in the canonical release matrix; package size and absence from a feature list
grant no additional exception. An individual repository cannot advance the
framework release line on its own.

Examples:

- `0.8.0` = OpenSwoole runtime foundations
- `0.9.0` = ORM stability rewrite
- `0.10.0` = configurable authentication failure handling and complete exception-filter lifecycle
- likely `1.0.0+` = entity-driven database sync
- likely `1.0.0+` = application runtime contexts and scheduling
- `1.0.0` = confidence release

For example, Core `0.11.0` with ORM `0.10.2` is an incomplete transition. Core
`0.11.1` and ORM `0.11.3` share the correct line. The established stream mapping is:

| Framework modules | RabbitMQ and Beanstalkd | Compatible Attributes line |
| --- | --- | --- |
| `0.9.x` | `1.0.x` | `1.0.x` |
| `0.10.x` | `1.1.x` | `1.0.x` |
| `0.11.x` | `1.2.x` | `1.0.x` |

The first two rows are published precedent; the last carries that coordination
forward for the `0.11` correction. Core's
[0.9 manifest](https://github.com/assegaiphp/core/blob/0.9.0/composer.json) and
[0.10 manifest](https://github.com/assegaiphp/core/blob/0.10.0/composer.json)
show synchronized helpers with Attributes `^1.0`. The adapters'
[RabbitMQ 1.1 notes](https://github.com/assegaiphp/rabbitmq/blob/1.1.0/docs/releases/1.1.0.md)
and [Beanstalkd 1.1 notes](https://github.com/assegaiphp/beanstalkd/blob/1.1.0/docs/releases/1.1.0.md)
document the Common `0.9` to `0.10` transition. Preserve this history when planning
the next release; do not infer a new framework major from another package's number.

## Module coverage

The canonical membership is [framework-modules.json](../meta/framework-modules.json).
It accounts for Attributes, Auth, Beanstalkd, CMS, Collections, Common, Console,
Core, Events, Forms, ORM, RabbitMQ, Starter, Util and Validation. Each module has
an explicit `framework`, `legacy-coordinated` or `stable-dependency` policy.
`releaseLines` maps the pre-existing streams for each reviewed framework line;
all `framework` entries use that framework line. Unknown lines and unclassified
modules block readiness. Verify the membership against
the active repository and published vendor catalogs for each release. New modules
must be included before their first publication; an unknown package requires
explicit classification rather than automatic exclusion.

Each entry also declares `released` or `unreleased` publication state. An
unreleased module's intended baseline remains aligned and accounted for, while
its unfinished implementation stays outside the current public release and
default install recommendations. A released module cannot depend on an
unreleased one. First publication requires explicit promotion through the same
readiness checks; a published package cannot be hidden under an unreleased label.

An application such as the framework website consumes the coordinated packages
and has its own documentation and deployment obligations. It is included in
release readiness even though it is not a framework library.

Patch releases may ship independently within the agreed `A.B`:

- do not silently break the active framework line
- keep Composer constraints honest
- keep repo health metadata honest
- update the framework packages that depend on them when a new version becomes the expected baseline

Existing version drift must be reported with exact published versions and
constraints and checked against release history. Preserve published tags and
releases; do not invent exceptions, relabel history, downgrade dependencies or
claim the transition is complete. The historical Attributes `10.1` tag remains
intact; the reviewed compatible `1.0` dependency stream is selected explicitly.

## What to do before each milestone release

Before tagging a milestone release:

1. Check the preceding releases, agree the framework target `A.B` and its mapped
   legacy streams with Andrew, and coordinate every module owner.
2. Record every module's current published version, planned version, dependent
   constraints, checks, release notes and owner readiness. Include unchanged
   modules; an unaccounted module blocks the major/minor release.
3. Coordinate with the AssegaiPHP Website owner before declaring release
   readiness. Prepare versioned guides, upgrade notes, the updates-advisor path,
   release article and relevant navigation/search checks for shipped behavior.
4. Verify the complete dependency plan and Console's version/update reporting.
   Every framework module must use the target `A.B`; pre-existing streams must
   match the reviewed canonical matrix and remain part of the same coordinated
   readiness checks. Unpublished targets must not become live recommendations.
5. Verify package checks and Website preparation before the first release tag.
   Andrew owns release branches, tags and releases; repository publishing still
   requires its separately scoped authorization.
6. Publish packages in dependency order under that authorization. Update the
   Website's Composer lock only after required packages are published, verify
   the real website, and deploy Website from `main` last through its protocol.
7. Check the actual published versions, Composer resolution and public docs
   before reporting the coordinated release complete.

## What the CLI should never do

The CLI should never guess the installed framework version from sibling repositories or from the CLI repo itself.

When it reports the app's Assegai version, it should read that from the target workspace itself, using:

1. the workspace's installed Composer metadata when available
2. the workspace's `composer.lock` as a fallback when installed metadata is not present

That keeps version reporting accurate even when the CLI and the app live in different repositories or different directories.

## Shared release data and enforcement

The membership inventory owns package identities and explicit retired entries.
Each coordinated release needs one reviewed inventory of its agreed line, exact
planned package versions, constraints and Website requirements. CLI owns the
shared reader and validator consumed by:

- the updates advisor page
- release notes scaffolding
- repo health badges
- future compatibility views

Consumers use an explicitly pinned inventory or a validated bundled snapshot;
ordinary commands must not fetch a moving branch or require sibling checkouts.
Generated check receipts bind the inventory digest, exact candidate source and
dependency identities. An editable ready flag or a version label is insufficient.

Preparation checks validate the planned graph and Website candidate before tags
exist. Publication checks separately verify the actual stable package versions,
source identities, Composer resolution and final Website state. Missing evidence
or a changed input invalidates readiness. The validator is read-only and grants
no git, tag, release or deployment authority.

The [0.11 inventory](../meta/releases/0.11.json) records the coordinated plan.
Its existence is not proof that the planned packages have been published.

## Repository enforcement

The [shared package workflow](../.github/workflows/package-policy.yml) invokes
Console's single policy reader using full commit pins for its source and Core's
release data. Module workflows run it before their existing build jobs. It uses
the locked semver-only tool profile, without installing the framework package
graph. Source references must be immutable before any policy checkout occurs.

The repository pre-push hooks use the same reader and pins. Set
`ASSEGAI_RELEASE_CONSOLE` to the reviewed Console checkout and
`ASSEGAI_RELEASE_CORE` to the reviewed Core data checkout, then install Console's
`tools/policy` lock with scripts and plugins disabled. The supplied files must
match their recorded commits. Keep any existing hooks when enabling the tracked
`.githooks` directory; do not replace another configured hook directory.

Hooks reject a pushed source different from the checked-out commit or an
uncommitted Composer manifest. Package checks reject mismatched dependency
lines, unexpected release/tag versions and release refs for unreleased modules.
Their passing result is a package-policy observation. Complete Prepare and
Publish checks still require the full module, dependency and Website evidence.

Tracked workflow and hook files take effect only after their source pins are
available and the corresponding checks are installed. They do not establish
remote branch protection or grant publishing permission. Preserve the separate
repository approvals and Andrew's authority over tags and releases.
