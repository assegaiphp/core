# Custom CLI Schematics In Depth

Use this guide when you already understand the basic idea of a custom schematic and want the details:

- how to design a manifest from scratch
- how tokens behave in paths and file contents
- how to scaffold more than one file type
- when declarative schematics stop being enough

## The mental model

Think about a schematic in three layers:

1. inputs
2. templates
3. targets

Inputs are the values the developer passes on the command line.

Templates are the source files inside the schematic folder.

Targets are the generated file paths inside the app.

If you can describe those three pieces clearly, the schematic is usually straightforward to build.

## Manifest reference

This is a realistic declarative manifest with several moving parts:

```json
{
  "name": "menu-sync",
  "aliases": ["ms"],
  "description": "Generate menu sync scaffolding.",
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
      "shortcut": "P",
      "description": "The external menu provider name.",
      "acceptValue": true,
      "valueRequired": true,
      "default": "internal"
    },
    {
      "name": "with-client",
      "description": "Generate a TypeScript client config too.",
      "acceptValue": false,
      "valueRequired": false
    }
  ],
  "templates": [
    {
      "source": "templates/service.php.stub",
      "target": "__SOURCE_ROOT__/__NAME__/__NAME__Service.php"
    },
    {
      "source": "templates/config.json.stub",
      "target": "config/__KEBAB__.json"
    },
    {
      "source": "templates/client.ts.stub",
      "target": "src/frontend/__KEBAB__.client.ts"
    }
  ]
}
```

How to read it:

- `name` is the command name after `assegai g`
- `aliases` adds shortcuts
- `arguments` define positional values
- `options` define named flags
- `templates` define what gets copied and where it goes

In v1, declarative schematics are intentionally limited to file generation and token replacement. They do not perform custom PHP logic or module mutation.

## Token reference

These tokens are available in declarative templates and in helper methods on `AbstractCustomSchematic`.

### Naming tokens

- `__NAME__`
  The resolved feature name in PascalCase.
- `__SINGULAR__`
  The singular PascalCase form.
- `__PLURAL__`
  The plural PascalCase form.
- `__CAMEL__`
  The feature name in camelCase.
- `__KEBAB__`
  The feature name in kebab-case.
- `__PASCAL__`
  The feature name in PascalCase.

### Path and namespace tokens

- `__SOURCE_ROOT__`
  Usually `src`
- `__BASE_NAMESPACE__`
  The app's root PSR-4 namespace
- `__CURRENT_NAMESPACE__`
  The namespace that matches the generated target path

### Input tokens

- `__ARG_<NAME>__`
- `__OPTION_<NAME>__`

Examples:

- `__ARG_NAME__`
- `__OPTION_PROVIDER__`
- `__OPTION_DOMAIN__`

## Combining tokens

Yes, you can combine them.

Token replacement is just string replacement. That means both of these are valid:

```text
__SOURCE_ROOT__/__NAME__/DTOs/Create__SINGULAR__DTO.php
__SOURCE_ROOT__/__NAME__/Integrations/__PASCAL____OPTION_PROVIDER__Sync.php
```

And this is valid inside file contents too:

```php
class __PASCAL____OPTION_PROVIDER__Client
{
}
```

If the user runs:

```bash
assegai g menu-sync rewards --provider=UberEats
```

then:

```text
__SOURCE_ROOT__/__NAME__/Integrations/__PASCAL____OPTION_PROVIDER__Sync.php
```

becomes:

```text
src/Rewards/Integrations/RewardsUberEatsSync.php
```

## Generating multiple file types

A schematic can generate any text file, not only PHP.

Example template list:

```json
[
  {
    "source": "templates/service.php.stub",
    "target": "__SOURCE_ROOT__/__NAME__/__NAME__Service.php"
  },
  {
    "source": "templates/provider.ts.stub",
    "target": "frontend/providers/__KEBAB__.ts"
  },
  {
    "source": "templates/config.yaml.stub",
    "target": "config/__KEBAB__.yaml"
  },
  {
    "source": "templates/runbook.md.stub",
    "target": "docs/runbooks/__KEBAB__.md"
  }
]
```

That is useful when your company workflow spans backend code, front-end support files, documentation, or operational config.

## Example: one command, several outputs

Imagine a `menu-sync` schematic that needs:

- a service class
- a DTO
- a TypeScript provider config
- a Markdown integration note

Manifest:

```json
{
  "name": "menu-sync",
  "kind": "declarative",
  "requiresWorkspace": true,
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
      "description": "The external provider name.",
      "acceptValue": true,
      "valueRequired": true,
      "default": "internal"
    }
  ],
  "templates": [
    {
      "source": "templates/service.php.stub",
      "target": "__SOURCE_ROOT__/__NAME__/__NAME__Service.php"
    },
    {
      "source": "templates/dto.php.stub",
      "target": "__SOURCE_ROOT__/__NAME__/DTOs/Create__SINGULAR__SyncDTO.php"
    },
    {
      "source": "templates/provider.ts.stub",
      "target": "frontend/providers/__KEBAB__.provider.ts"
    },
    {
      "source": "templates/readme.md.stub",
      "target": "docs/integrations/__KEBAB__.md"
    }
  ]
}
```

Run:

```bash
assegai g menu-sync catalog --provider=uber-eats
```

That one command can create a small, repeatable integration slice instead of one PHP file.

## Designing good arguments and options

A good argument is something the developer almost always has to provide.

Typical examples:

- the feature name
- the domain name
- the provider name

A good option is something that changes the output shape or content.

Typical examples:

- `--provider=uber-eats`
- `--domain=finance`
- `--with-client`

Practical rule:

- use an argument for the main subject
- use options for variations

## When declarative stops being enough

Move to a class-backed schematic when:

- some files should only be written when an option is present
- the target path depends on rules you cannot express cleanly as tokens
- you need to compose content before writing it
- generation should branch by company-specific logic

Class-backed schematics use:

```php
use Assegai\Console\Core\Schematics\Custom\AbstractCustomSchematic;
```

and receive a `SchematicContext`.

That context gives you:

- arguments
- options
- workspace path
- naming tokens
- output helpers

## Minimal class-backed example

```php
<?php

namespace Assegai\App\Schematics;

use Assegai\Console\Core\Schematics\Custom\AbstractCustomSchematic;

class MenuSyncSchematic extends AbstractCustomSchematic
{
  public function build(): int
  {
    $provider = (string) $this->context()->getOption('provider', 'internal');
    $template = $this->loadTemplate('templates/service.php.stub');
    $content = $this->replaceTokens($template . PHP_EOL . '// Provider: __OPTION_PROVIDER__' . PHP_EOL);

    return $this->writeRelativeFile(
      '__SOURCE_ROOT__/__NAME__/Integrations/__PASCAL__' . ucfirst($provider) . 'Sync.php',
      $content
    );
  }
}
```

That is the point where you stop thinking in terms of "copy this file" and start thinking in terms of "run generation logic".

## Package-backed schematics

If a team uses the same schematic in many projects, ship it through Composer.

Register the manifests in the package:

```json
{
  "extra": {
    "assegai": {
      "schematics": [
        "resources/menu-sync/schematic.json",
        "resources/loyalty/schematic.json"
      ]
    }
  }
}
```

Then install the package in the workspace and use `assegai schematic:list` to verify that the CLI discovered it.

## Troubleshooting

If a schematic is not showing up:

1. run `assegai schematic:list`
2. confirm the manifest path is correct
3. confirm the workspace `assegai.json` has not disabled local or package discovery
4. confirm the manifest name or aliases do not collide with a built-in schematic
5. confirm class-backed schematics point to a real handler class

If generation runs but the output is wrong:

1. inspect the `target` path first
2. inspect token names next
3. remember that token replacement is text-based, so spelling matters exactly

## Related guides

- [Custom CLI Schematics](./custom-cli-schematics.md)
- [Building a Feature](./building-a-feature.md)
