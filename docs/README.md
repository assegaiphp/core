# Assegai Guides

Use these guides to go from a blank environment to a working Assegai app, then grow that app feature by feature.

They focus on the tasks most people need first:

- installing the CLI
- creating and serving a project
- generating REST features and pages
- organizing code with modules and providers
- handling requests with DTOs and validation
- rendering HTML with HTMX and Web Components
- working with the ORM, queues, and API docs when the app grows

## Recommended reading order

1. [Getting Started](./getting-started.md)
2. [Custom CLI Schematics](./custom-cli-schematics.md)
3. [Frontend with Web Components](./frontend-with-web-components.md)
4. [Building a Feature](./building-a-feature.md)
5. [Architecture and Lifecycle](./architecture-and-lifecycle.md)
6. [Modules and Providers](./modules-and-providers.md)
7. [Controllers and Routing](./controllers-and-routing.md)
8. [Request Data and Validation](./request-data-and-validation.md)
9. [API Docs and Clients](./api-docs-and-clients.md)
10. [Pages, Components, HTMX, and Web Components](./pages-and-components.md)
11. [Data and ORM](./data-and-orm.md)
12. [ORM Setup and Data Sources](./orm-setup-and-data-sources.md)
13. [ORM Entities, Repositories, and Results](./orm-entities-repositories-and-results.md)
14. [ORM Relations](./orm-relations.md)
15. [ORM Migrations and Database Workflows](./orm-migrations-and-database-workflows.md)
16. [Custom CLI Schematics In Depth](./custom-cli-schematics-in-depth.md)
17. [Guards, Interceptors, Pipes, and Middleware](./guards-interceptors-pipes-and-middleware.md)
18. [Queues and Background Jobs](./queues-and-background-jobs.md)

## Guide map

### Fundamentals

- [Getting Started](./getting-started.md) introduces the CLI, the generated workspace, and the first running app.
- [Custom CLI Schematics](./custom-cli-schematics.md) shows how to teach `assegai generate` about company-specific scaffolds.
- [Frontend with Web Components](./frontend-with-web-components.md) shows where front-end code should live, how the first-party Web Components runtime works, and how to upgrade older `main.js` projects.
- [Architecture and Lifecycle](./architecture-and-lifecycle.md) explains how requests move through modules, controllers, providers, and responders.
- [Modules and Providers](./modules-and-providers.md) covers dependency injection, module boundaries, and configuration.
- [Controllers and Routing](./controllers-and-routing.md) is the main HTTP guide, including params, bodies, headers, status codes, redirects, and host-based routing.

### Techniques

These guides are about working style and day-to-day delivery rather than one isolated framework surface.

- [Building a Feature](./building-a-feature.md) shows the happy path from scaffolded resource to a real feature.
- [Custom CLI Schematics](./custom-cli-schematics.md) shows how to create local and package-backed generators for your own domain.
- [Custom CLI Schematics In Depth](./custom-cli-schematics-in-depth.md) goes deeper into manifest design, token usage, combined tokens, and non-PHP outputs.
- [Frontend with Web Components](./frontend-with-web-components.md) shows the supported front-end workflow for `.wc.ts` files, `serve --dev`, and legacy-project upgrades.
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
assegai api:export openapi
open http://localhost:5000/docs
assegai g r posts
assegai g pg about
```

From there you already have:

- a running app
- an exported OpenAPI document plus a docs route once the spec is current
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
- `assegaiphp/auth` for session and JWT authentication strategies that you can wire into your own login flow
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
