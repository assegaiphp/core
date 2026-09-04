<div align="center" style="padding-bottom: 48px">
  <a href="https://assegaiphp.com/" target="blank"><img src="https://assegaiphp.com/images/logos/logo-cropped.png" width="200" alt="AssegaiPHP Logo"></a>
</div>

<p align="center">
  <a href="https://github.com/assegaiphp/core/releases"><img alt="Latest release" src="https://img.shields.io/github/v/release/assegaiphp/core?display_name=tag&sort=semver&style=flat-square"></a>
  <a href="https://github.com/assegaiphp/core/actions/workflows/php.yml"><img alt="Tests" src="https://img.shields.io/github/actions/workflow/status/assegaiphp/core/php.yml?branch=main&label=tests&style=flat-square"></a>
  <img alt="PHP 8.4+" src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php&logoColor=white">
  <a href="https://github.com/assegaiphp/core/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/github/license/assegaiphp/core?style=flat-square"></a>
  <img alt="Status active" src="https://img.shields.io/badge/status-active-10b981?style=flat-square">
</p>

# AssegaiPHP Core

<p align="center">A structured PHP framework for building modular APIs and server-side applications.</p>

AssegaiPHP gives PHP teams explicit application structure from the beginning: modules define feature boundaries, dependency injection wires behavior together, attributes keep routing and request binding close to handlers, and the official CLI scaffolds the parts as one coherent feature.

The architecture is inspired by NestJS, expressed with modern PHP 8.4 features and conventions.

## Why AssegaiPHP

- **Structure that survives growth:** controllers, providers, modules, DTOs, and repositories have distinct responsibilities.
- **A complete API workflow:** the same metadata drives request binding, validation, OpenAPI documentation, Postman exports, and typed TypeScript clients.
- **Productive scaffolding:** generate projects, resources, pages, components, and other framework artifacts without hand-wiring the module graph.
- **One server-side application model:** build JSON APIs, server-rendered pages, HTMX interactions, and hydrated Web Components without introducing a second backend architecture.

## Framework at a glance

Core provides:

- modular application composition and dependency injection
- attribute-based controllers, routing, parameter binding, and host routing
- DTO hydration and validation
- guards, interceptors, pipes, middleware, and exception filters
- generated OpenAPI metadata and runtime documentation endpoints
- application-level CORS policy
- server-rendered Twig views, components, HTMX, and Web Components integration
- the default PHP runtime and an experimental OpenSwoole runtime

## Requirements and maturity

- PHP 8.4 or newer
- Composer 2

AssegaiPHP is actively developed and currently remains on a pre-1.0 release line. The normal PHP runtime is the recommended default. OpenSwoole support is experimental and should be adopted only after testing it against your application's workload and dependencies.

## Getting started

The recommended application workflow starts with the Assegai Console:

```bash
composer global require assegaiphp/console:^0.10
assegai new blog-api
cd blog-api
assegai generate resource posts
assegai api:export openapi
assegai serve
```

The resource generator creates a controller, provider, module, entity, DTOs, and CRUD-style routes, then imports the feature into the application module. Once the server is running, open:

- `http://localhost:5000/docs` for Swagger UI
- `http://localhost:5000/openapi.json` for the generated OpenAPI document

See [Getting Started](./docs/getting-started.md) for the complete walkthrough.

## The application structure

Assegai keeps transport, application behavior, and composition separate:

```php
<?php

namespace App\Posts;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;

#[Injectable]
class PostsService
{
  public function findAll(): array
  {
    return [];
  }
}

#[Controller('posts')]
readonly class PostsController
{
  public function __construct(private PostsService $postsService)
  {
  }

  #[Get]
  public function findAll(): array
  {
    return $this->postsService->findAll();
  }
}

#[Module(
  providers: [PostsService::class],
  controllers: [PostsController::class],
)]
class PostsModule
{
}
```

Controllers own HTTP concerns, providers own application behavior, and modules make their relationships explicit. Start with [Modules and Providers](./docs/modules-and-providers.md) and [Controllers and Routing](./docs/controllers-and-routing.md) for the full model.

## API contracts and clients

AssegaiPHP can derive an API contract from controllers, DTOs, validation attributes, and response metadata:

```bash
assegai api:export openapi
assegai api:export postman
assegai api:client typescript
```

This keeps the implementation, interactive documentation, exported contracts, and frontend client generated from the same metadata graph. See [API Docs and Clients](./docs/api-docs-and-clients.md).

## Cross-origin applications

Configure CORS at the application boundary when a browser client and API use different origins:

```php
$app->enableCors([
  'origin' => ['http://localhost:5173'],
  'credentials' => true,
  'maxAge' => 600,
]);
```

The policy covers real preflight requests as well as successful and error responses. See [Cross-Origin Resource Sharing](./docs/cors.md) for every option and migration guidance.

## Beyond JSON APIs

The same application can render classic Twig views with `view(...)`, component-backed pages with `render(...)`, HTMX interactions, and hydrated Web Components. See [Pages and Components](./docs/pages-and-components.md).

Data-backed applications can add the official ORM for entities, repositories, relations, query building, and migrations. See [Data and ORM](./docs/data-and-orm.md).

## Core and the official ecosystem

AssegaiPHP is a collection of focused Composer packages. Not every capability is bundled into `assegaiphp/core`.

| Package | Responsibility |
|---|---|
| `assegaiphp/core` | Application runtime, modules, DI, controllers, routing, rendering, and request lifecycle |
| `assegaiphp/console` | Project creation, scaffolding, serving, contract export, client generation, and maintenance commands |
| `assegaiphp/validation` | Rule- and attribute-based DTO validation |
| `assegaiphp/orm` | Entities, repositories, relations, migrations, and SQL-family data sources |
| `assegaiphp/auth` | Session, JWT, and OAuth authentication strategies |
| `assegaiphp/events` | Application and domain events |
| `assegaiphp/beanstalkd` / `assegaiphp/rabbitmq` | Queue transports and background job processing |

Use the CLI to create an application. Install Core directly when embedding or extending the framework runtime itself:

```bash
composer require assegaiphp/core:^0.10
```

## Documentation and support

- [Guide](https://assegaiphp.com/guide)
- [Core documentation](./docs/README.md)
- [Support](https://assegaiphp.com/support)
- [Release notes](./docs/releases)

GitHub issues are reserved for reproducible bug reports and feature requests. Read the [issue reporting checklist](./CONTRIBUTING.md#issues-and-bugs) before opening one.

For repository contribution and pull request conventions, see [Commit and PR Guidelines](./docs/commit-and-pr-guidelines.md).

## Stay in touch

- Author: [Andrew Masiye](https://twitter.com/feenix11)
- Website: [assegaiphp.com](https://assegaiphp.com/)
- X: [@assegaiphp](https://twitter.com/assegaiphp)

## License

AssegaiPHP Core is [MIT licensed](LICENSE).
