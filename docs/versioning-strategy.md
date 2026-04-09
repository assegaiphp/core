# Versioning Strategy

This document explains how we should talk about versions across AssegaiPHP.

The main goal is simple:

- users should have one clear answer to "what version of Assegai am I on?"
- package maintainers should still be able to release packages responsibly
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

`assegaiphp/core` defines the framework milestone line.

Examples:

- `0.8.0` = OpenSwoole runtime foundations
- `0.9.0` = ORM stability rewrite
- likely `1.0.0+` = entity-driven database sync
- likely `1.0.0+` = application runtime contexts and scheduling
- `1.0.0` = confidence release

Packages that are part of that milestone should align with the same minor line when they ship coordinated user-facing work.

Examples:

- if `orm` is a headline part of `0.9.0`, it should ship on the `0.9.x` line
- if `console` needs new upgrade or scaffolding behavior for `0.9.0`, it should also ship on the `0.9.x` line
- if `database:sync` becomes a post-`1.0.0` milestone, `orm` and `console` should align to that release line together
- if application runtime contexts and scheduling become a post-`1.0.0` milestone, `core` and `console` should align to that release line together

This keeps release notes, upgrade notes, and support answers easier to follow.

## The rule for smaller shared packages

Not every package has to move in lockstep for every patch.

Smaller shared packages such as utility libraries may still release independently when that makes sense.

But they should still follow these rules:

- do not silently break the active framework line
- keep Composer constraints honest
- keep repo health metadata honest
- update the framework packages that depend on them when a new version becomes the expected baseline

## What to do before each milestone release

Before tagging a milestone release:

1. confirm the target `assegaiphp/core` version
2. confirm which first-party packages are part of that milestone
3. align their package versions to the same release line where needed
4. make sure `console` reports the correct CLI version and installed framework version
5. prepare upgrade notes and the updates advisor entry for the new release path

## What the CLI should never do

The CLI should never guess the installed framework version from sibling repositories or from the CLI repo itself.

When it reports the app's Assegai version, it should read that from the target workspace itself, using:

1. the workspace's installed Composer metadata when available
2. the workspace's `composer.lock` as a fallback when installed metadata is not present

That keeps version reporting accurate even when the CLI and the app live in different repositories or different directories.

## Future work

The next useful step after this policy is a small release manifest that can be consumed by:

- the updates advisor page
- release notes scaffolding
- repo health badges
- future compatibility views

That would let us keep one source of truth for release lines while still publishing separate Composer packages.
