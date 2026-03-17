# Assegai Guides

These guides are written for AssegaiPHP as a whole, not just `assegaiphp/core`.

They are written for developers who want to install Assegai, scaffold a project, and build features in their own environment.

They are grounded in:

- the current Assegai CLI workflow
- a freshly scaffolded Assegai app
- generated `resource` and `page` schematics
- the current `assegaiphp/core`, `assegaiphp/orm`, and `assegaiphp/validation` package behavior
- the official [Assegai guide](https://assegaiphp.com/guide), the [AssegaiPHP GitHub organization](https://github.com/assegaiphp), and the general architectural direction of [NestJS](https://docs.nestjs.com/)

The goal is to show how Assegai helps you move quickly without giving up structure:

- create a project from the CLI
- serve it immediately
- generate REST resources and pages
- organize code with modules and providers
- inject dependencies instead of wiring everything by hand
- use the ORM when you want real persistence
- move background work onto queues when a request should stay fast

## Recommended reading order

1. [Getting Started](./getting-started.md)
2. [Building a Feature](./building-a-feature.md)
3. [Architecture and Lifecycle](./architecture-and-lifecycle.md)
4. [Modules and Providers](./modules-and-providers.md)
5. [Controllers and Routing](./controllers-and-routing.md)
6. [Request Data and Validation](./request-data-and-validation.md)
7. [API Docs and Clients](./api-docs-and-clients.md)
8. [Pages, Components, HTMX, and Web Components](./pages-and-components.md)
9. [Data and ORM](./data-and-orm.md)
10. [ORM Setup and Data Sources](./orm-setup-and-data-sources.md)
11. [ORM Entities, Repositories, and Results](./orm-entities-repositories-and-results.md)
12. [ORM Relations](./orm-relations.md)
13. [ORM Migrations and Database Workflows](./orm-migrations-and-database-workflows.md)
14. [Guards, Interceptors, Pipes, and Middleware](./guards-interceptors-pipes-and-middleware.md)
15. [Queues and Background Jobs](./queues-and-background-jobs.md)

## Guide map

### Fundamentals

- [Getting Started](./getting-started.md) introduces the CLI, the generated workspace, and the first running app.
- [Architecture and Lifecycle](./architecture-and-lifecycle.md) explains how requests move through modules, controllers, providers, and responders.
- [Modules and Providers](./modules-and-providers.md) covers dependency injection, module boundaries, and configuration.
- [Controllers and Routing](./controllers-and-routing.md) is the main HTTP guide, including params, bodies, headers, status codes, redirects, and host-based routing.

### Techniques

These guides are about working style and day-to-day delivery rather than one isolated framework surface.

- [Building a Feature](./building-a-feature.md) shows the happy path from scaffolded resource to a real feature.
- [Request Data and Validation](./request-data-and-validation.md) shows how to keep transport concerns at the edge with DTOs and pipes.
- [API Docs and Clients](./api-docs-and-clients.md) covers `/docs`, `/openapi.json`, Postman export, and the TypeScript client generator.
- [Pages, Components, HTMX, and Web Components](./pages-and-components.md) covers server-rendered UI patterns and the new Web Components workflow.
- [Data and ORM](./data-and-orm.md) is the ORM map, including the practical techniques that keep data-heavy features maintainable.

### Data and Persistence

- [ORM Setup and Data Sources](./orm-setup-and-data-sources.md) covers installation, CLI database setup, data source resolution, and repository injection.
- [ORM Entities, Repositories, and Results](./orm-entities-repositories-and-results.md) explains entity modeling, CRUD patterns, and the result objects controllers can return directly.
- [ORM Relations](./orm-relations.md) is the relation guide for `OneToOne`, `ManyToOne`, `OneToMany`, and `ManyToMany`, with a strong focus on ownership and loading.
- [ORM Migrations and Database Workflows](./orm-migrations-and-database-workflows.md) covers schema evolution and the CLI commands around migrations, loading, and seeding.
- [Queues and Background Jobs](./queues-and-background-jobs.md) covers work that should move off the request path.

## What makes Assegai feel fast

The shortest happy path looks like this:

```bash
assegai new blog-api
cd blog-api
assegai serve
open http://localhost:5000/docs
assegai g r posts
assegai g pg about
```

From there you already have:

- a running app
- generated API docs at `/docs`
- a root module
- a home page
- a generated CRUD-style `posts` feature
- a generated `about` page backed by a component
- automatic `AppModule` import updates when generators are run from the project root

## Core ideas

Assegai is easiest to understand when you see it as a few cooperating concepts:

- modules define boundaries
- controllers speak HTTP
- providers hold application logic
- DTOs shape input
- entities shape persistence
- declarations, HTMX, and Web Components shape rendered pages
- responders turn handler return values into JSON or HTML
- the CLI keeps those conventions easy to create and maintain

## Broader ecosystem

Assegai is more than one package. The current public organization and guide surface show an ecosystem around the core framework, including:

- `assegaiphp/console` for project scaffolding and day-to-day CLI workflows
- `assegaiphp/core` for modules, controllers, providers, routing, rendering, guards, interceptors, and pipes
- `assegaiphp/orm` for entity mapping and repository-backed data access
- `assegaiphp/validation` for DTO validation attributes
- `assegaiphp/forms` for form handling
- `assegaiphp/rabbitmq` and `assegaiphp/beanstalkd` for queue-backed background work
- `assegaiphp/util`, `assegaiphp/common`, and `assegaiphp/collections` as supporting libraries

## ORM track

If your app is data-backed, the ORM reading path is:

1. [Data and ORM](./data-and-orm.md)
2. [ORM Setup and Data Sources](./orm-setup-and-data-sources.md)
3. [ORM Entities, Repositories, and Results](./orm-entities-repositories-and-results.md)
4. [ORM Relations](./orm-relations.md)
5. [ORM Migrations and Database Workflows](./orm-migrations-and-database-workflows.md)

## Notes on accuracy

These docs intentionally prefer verified behavior over broad claims. Where a feature is scaffolded but still benefits from a manual follow-up step, the guide says so directly.
