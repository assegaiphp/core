# Custom CLI Schematics

Assegai's built-in generators cover the framework's common cases.

Custom schematics let you teach the CLI about your own domain.

That means you can create commands such as:

- `assegai g loyalty-program rewards`
- `assegai g menu-sync ubereats`
- `assegai g tenant-onboarding enterprise`

without forking the CLI.

## What a schematic actually is

A schematic is just a recipe for generation.

At the simplest level, the recipe says:

1. which arguments and options the command accepts
2. which template files to read
3. where the generated files should be written
4. which placeholder values should be replaced

If you need more than file copying and token replacement, you can move to a PHP-backed schematic and write custom logic.

## Start with the easiest path

If you are new to custom schematics, start with a declarative schematic.

Generate a starter:

```bash
assegai schematic:init loyalty-program
```

That gives you a folder like this:

```text
schematics/loyalty-program/
  schematic.json
  templates/
    service.php.stub
```

This is enough for a working custom generator.

## A first declarative example

Example manifest:

```json
{
  "name": "loyalty-program",
  "aliases": ["lp"],
  "description": "Generate loyalty program scaffolding.",
  "requiresWorkspace": true,
  "kind": "declarative",
  "arguments": [
    {
      "name": "name",
      "description": "The feature name to generate.",
      "required": true
    }
  ],
  "options": [
    {
      "name": "provider",
      "description": "The loyalty provider name.",
      "acceptValue": true,
      "valueRequired": true,
      "default": "internal"
    }
  ],
  "templates": [
    {
      "source": "templates/service.php.stub",
      "target": "__SOURCE_ROOT__/__NAME__/__NAME__Service.php"
    }
  ]
}
```

Example template:

```php
<?php

namespace __CURRENT_NAMESPACE__;

class __NAME__Service
{
  public string $provider = '__OPTION_PROVIDER__';
}
```

Run it:

```bash
assegai g loyalty-program rewards --provider=partner-plus
```

Result:

```text
src/Rewards/RewardsService.php
```

The generated file will contain:

```php
<?php

namespace Assegaiphp\BlogApi\Rewards;

class RewardsService
{
  public string $provider = 'partner-plus';
}
```

## How to read the manifest

If you have never built a schematic before, this is the part that matters most.

- `name`: the command name after `assegai g`
- `aliases`: shorter names such as `lp`
- `description`: help text shown in the CLI
- `requiresWorkspace`: whether the schematic only works inside an Assegai app
- `kind`: `declarative` for template-only generation, `class` for PHP-backed generation
- `arguments`: positional inputs such as the feature name
- `options`: named flags such as `--provider=...`
- `templates`: for declarative schematics, the files to copy and where to write them
- `handler`: for class-backed schematics, the PHP class that performs the generation

The most important pair is:

- `source`: where the template file lives inside the schematic folder
- `target`: where the generated file should be written in the app

## How template tokens work

Tokens are simple text placeholders.

When the schematic runs, Assegai replaces them with resolved values.

Built-in naming tokens include:

- `__NAME__`
- `__SINGULAR__`
- `__PLURAL__`
- `__CAMEL__`
- `__KEBAB__`
- `__PASCAL__`
- `__BASE_NAMESPACE__`
- `__CURRENT_NAMESPACE__`
- `__SOURCE_ROOT__`

You also get tokens for custom inputs:

- `__ARG_<NAME>__`
- `__OPTION_<NAME>__`

Examples:

- `__ARG_NAME__`
- `__OPTION_PROVIDER__`

## Can you combine tokens?

Yes.

Token replacement is text-based, so you can combine tokens anywhere they make sense.

Examples:

```text
__SOURCE_ROOT__/__NAME__/DTOs/Create__SINGULAR__DTO.php
__SOURCE_ROOT__/__NAME__/__PASCAL____OPTION_PROVIDER__Client.php
docs/__KEBAB__-integration.md
```

If the user runs:

```bash
assegai g loyalty-program rewards --provider=PartnerPlus
```

then a target like this:

```text
__SOURCE_ROOT__/__NAME__/__PASCAL____OPTION_PROVIDER__Client.php
```

becomes:

```text
src/Rewards/RewardsPartnerPlusClient.php
```

## Can you generate files that are not PHP?

Yes.

A schematic is not limited to PHP files. It can generate any text file.

For example:

```json
{
  "templates": [
    {
      "source": "templates/client.ts.stub",
      "target": "__SOURCE_ROOT__/__NAME__/clients/__KEBAB__.client.ts"
    },
    {
      "source": "templates/config.json.stub",
      "target": "config/__KEBAB__.json"
    },
    {
      "source": "templates/notes.md.stub",
      "target": "docs/__KEBAB__.md"
    }
  ]
}
```

That means one schematic can generate PHP, TypeScript, JSON, Markdown, YAML, or plain text files together.

## When to move to a class-backed schematic

Use a class-backed schematic when the generator needs logic, not just templates.

Typical reasons:

- some files should only be generated when an option is present
- the output path depends on custom rules
- you want to assemble content in PHP before writing the file
- you want more control than declarative templates provide

Starter command:

```bash
assegai schematic:init menu-sync --php
```

That creates a manifest plus a handler class that extends `AbstractCustomSchematic`.

## Share schematics through Composer packages

If your team uses the same schematic across projects, you can ship it in a package.

In the package `composer.json`:

```json
{
  "extra": {
    "assegai": {
      "schematics": [
        "resources/menu-sync/schematic.json"
      ]
    }
  }
}
```

Once the package is installed in the workspace, `assegai g` can discover it.

## See what the CLI found

Use:

```bash
assegai schematic:list
```

This shows:

- built-in schematics
- local schematics from `schematics/`
- package schematics from installed Composer packages

It is the quickest way to debug discovery issues.

## Discovery config

The workspace config lives in `assegai.json`:

```json
{
  "cli": {
    "schematics": {
      "paths": ["schematics"],
      "discoverPackages": true,
      "allowOverrides": false
    }
  }
}
```

## Next step

If you want the full token reference, more complete manifest examples, and guidance for generating multi-file company scaffolds, continue with [Custom CLI Schematics In Depth](./custom-cli-schematics-in-depth.md).
