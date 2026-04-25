<div align="center" style="padding-bottom: 48px">
    <a href="https://assegaiphp.com/" target="blank"><img src="https://assegaiphp.com/images/logos/logo-cropped.png" width="200" alt="Assegai Logo"></a>
</div>

<p align="center">
  <a href="https://github.com/assegaiphp/core/releases"><img alt="Latest release" src="https://img.shields.io/github/v/release/assegaiphp/core?display_name=tag&sort=semver&style=flat-square"></a>
  <a href="https://github.com/assegaiphp/core/actions/workflows/php.yml"><img alt="Tests" src="https://img.shields.io/github/actions/workflow/status/assegaiphp/core/php.yml?branch=main&label=tests&style=flat-square"></a>
  <img alt="PHP 8.4+" src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php&logoColor=white">
  <a href="https://github.com/assegaiphp/core/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/github/license/assegaiphp/core?style=flat-square"></a>
  <img alt="Status active" src="https://img.shields.io/badge/status-active-10b981?style=flat-square">
</p>

<p style="text-align: center">A progressive <a href="https://php.net">PHP</a> framework for building effecient and scalable server-side applications.</p>

## Description

Assegai is a framework for building efficient, scalable <a href="https://php.net" target="blank">PHP</a> server-side applications. It uses modern PHP (PHP 8.4+) and combines elements of OOP (Object Oriented Programming) and FP (Functional Programming).

## Contribution workflow

For commit and pull request conventions in this repo, see:

- [docs/commit-and-pr-guidelines.md](./docs/commit-and-pr-guidelines.md)

## Philosophy

<p>In recent years, PHP has gained a lot of features out the box that make it a really compelling language for developers. Assegai aims to take advantage of these wonderful features and provide an application architecture which allows for the effortless creation of highly testable, scalable, loosely coupled and easily maintainable applications. The architecture is heavily inspired by Nestjs.</p>

## Getting started

### Quick Start

```bash
$ composer require assegaiphp/core
```

For a real application, the recommended path is still the CLI:

```bash
$ assegai new my-app
```

Then use Core directly when you want to understand or extend the framework runtime itself.

### Minimal bootstrap

```php
<?php
// <path-to-project>/index.php

if (!isset($_GET['path']) || $_GET['path'] === '') {
  $_GET['path'] = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
}

require_once __DIR__ . '/bootstrap.php';
```

Bootstrap the app:

```php
<?php
// <path-to-project>/bootstrap.php

use Assegai\Core\AssegaiFactory;
use App\AppModule;

require __DIR__ . '/vendor/autoload.php';

function bootstrap(): void
{
  $app = AssegaiFactory::createFromProject(AppModule::class, __DIR__);
  $app->run();
}

bootstrap();
```

Start the development server:

```bash
$ assegai serve
```

For the fuller walkthrough, start with [Getting Started](./docs/getting-started.md).

### Server-rendered UI, HTMX, and Web Components

Assegai is not JSON-only. The framework supports classic server-rendered views through `view(...)`, component-backed pages through `render(...)`, automatic HTMX inclusion in rendered HTML, and first-class Web Components hydration through safe `data-props` helpers plus automatic bundle injection.

For the full walkthrough, see [Pages and Components](./docs/pages-and-components.md).

### Data, ORM, and Relations

For data-backed applications, Assegai ships with a TypeORM-inspired workflow around modules, repositories, entities, and migrations. The fuller persistence track now lives in:

- [Data and ORM](./docs/data-and-orm.md)
- [ORM Setup and Data Sources](./docs/orm-setup-and-data-sources.md)
- [ORM Entities, Repositories, and Results](./docs/orm-entities-repositories-and-results.md)
- [ORM Relations](./docs/orm-relations.md)
- [ORM Migrations and Database Workflows](./docs/orm-migrations-and-database-workflows.md)

### Constrained Route Params

Assegai routes support constrained dynamic params using angle-bracket syntax:

```php
#[Get(':id<int>')]
public function findById(#[Param('id')] int $id): object
{
  // ...
}
```

Built-in constraints currently include `int`, `slug`, `uuid`, `alpha`, `alnum`, `hex`, and `ulid`.

For the full guide set, visit [assegaiphp.com/guide](https://assegaiphp.com/guide).

## Questions

For questions and support, use the official guide and support pages:

- [Guide](https://assegaiphp.com/guide)
- [Support](https://assegaiphp.com/support)

The issue list of this repo is **exclusively** for bug reports and feature requests.

## Issues

Please make sure to read the [Issues Reporting Checklist](./CONTRIBUTING.md#issues-and-bugs) before opening an issue. Issues not conforming to the guidelines may be closed immediately.

## Consulting

With official support, you can get expert help straight from the Assegai core team. We provide dedicated technical support, migration strategies, advice on best practices and design decisions, PR reviews, and team augmentation. Read more about [support here](https://assegaiphp.com/support).

## Support

Assegai is an MIT-licensed open source project. It can grow thanks to sponsors and support by the amazing backers. If you'd like to join them, please [read more here](https://assegaiphp.com/support).

## Stay in touch

* Author - [Andrew Masiye](https://twitter.com/feenix11)
* Website - [https://assegaiphp.com](https://assegaiphp.com/)
* Twitter - [@assegaiphp](https://twitter.com/assegaiphp)

## License

Assegai is [MIT licensed](LICENSE).
