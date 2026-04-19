# Assegai Guides

Use these guides to go from a blank environment to a working Assegai app, then grow that app feature by feature.

They focus on the tasks most people need first:

- installing the CLI
- creating and serving a project
- learning the main workflow through a full tutorial
- generating REST features and pages
- organizing code with modules and providers
- handling requests with DTOs and validation
- rendering HTML with HTMX and Web Components
- working with authentication, events, the ORM, queues, and API docs when the app grows

## Recommended reading order

1. [Getting Started](./getting-started.md)
2. [Authentication](./authentication.md)
3. [Build a Kitchen Orders API](./tutorial-build-a-kitchen-orders-api.md)
4. [Custom CLI Schematics](./custom-cli-schematics.md)
5. [Serving with OpenSwoole](./serving-with-openswoole.md)
6. [Events and Domain Events](./events-and-domain-events.md)
7. [Frontend with Web Components](./frontend-with-web-components.md)
8. [Building a Feature](./building-a-feature.md)
9. [Architecture and Lifecycle](./architecture-and-lifecycle.md)
10. [Modules and Providers](./modules-and-providers.md)
11. [Controllers and Routing](./controllers-and-routing.md)
12. [Request Data and Validation](./request-data-and-validation.md)
13. [Authentication and OAuth In Depth](./authentication-and-oauth.md)
14. [API Docs and Clients](./api-docs-and-clients.md)
15. [Pages, Components, HTMX, and Web Components](./pages-and-components.md)
16. [Data and ORM](./data-and-orm.md)
17. [Getting Started](./orm-getting-started.md)
18. [Data Sources](./orm-data-sources.md)
19. [Entities](./orm-entities.md)
20. [Relations](./orm-relations.md)
21. [Migrations](./orm-migrations.md)
22. [Working with Entity Manager](./orm-entity-manager.md)
23. [Query Builder](./orm-query-builder.md)
24. [Drivers](./orm-drivers.md)
25. [Custom CLI Schematics In Depth](./custom-cli-schematics-in-depth.md)
26. [Events In Depth](./events-and-domain-events-in-depth.md)
27. [OpenSwoole Runtime](./openswoole-runtime.md)
28. [Guards, Interceptors, Pipes, and Middleware](./guards-interceptors-pipes-and-middleware.md)
29. [Queues and Background Jobs](./queues-and-background-jobs.md)

## Guide map

### Fundamentals

- [Getting Started](./getting-started.md) introduces the CLI, the generated workspace, and the first running app.
- [Authentication](./authentication.md) introduces session auth, JWT auth, and the current OAuth flow without assuming you already know which one your app needs.
- [Custom CLI Schematics](./custom-cli-schematics.md) shows how to teach `assegai generate` about company-specific scaffolds.
- [Serving with OpenSwoole](./serving-with-openswoole.md) shows how to boot the alternate long-lived runtime and what requirements it has before you switch.
- [Events and Domain Events](./events-and-domain-events.md) shows how to publish events, listen with `#[OnEvent(...)]`, and keep feature code decoupled without moving straight to queues.
- [Frontend with Web Components](./frontend-with-web-components.md) shows where front-end code should live, how the first-party Web Components runtime works, and how to upgrade older `main.js` projects.
- [Architecture and Lifecycle](./architecture-and-lifecycle.md) explains how requests move through modules, controllers, providers, and responders.
- [Modules and Providers](./modules-and-providers.md) covers dependency injection, module boundaries, and configuration.
- [Controllers and Routing](./controllers-and-routing.md) is the main HTTP guide, including params, bodies, headers, status codes, redirects, and host-based routing.

### Techniques

This section is about working style and day-to-day delivery rather than one isolated framework surface.

- [Build a Kitchen Orders API](./tutorial-build-a-kitchen-orders-api.md) is the first end-to-end tutorial and teaches the primary Assegai workflow in one pass.
- [Building a Feature](./building-a-feature.md) shows the happy path from scaffolded resource to a real feature.
- [Custom CLI Schematics](./custom-cli-schematics.md) shows how to create local and package-backed generators for your own domain.
- [Custom CLI Schematics In Depth](./custom-cli-schematics-in-depth.md) goes deeper into manifest design, token usage, combined tokens, and non-PHP outputs.
- [Events In Depth](./events-and-domain-events-in-depth.md) goes deeper into wildcard behavior, readiness, naming, and the boundary between events and queues.
- [OpenSwoole Runtime](./openswoole-runtime.md) explains the current alternate runtime path, lifecycle behavior, config, and the remaining experimental boundary.
- [Frontend with Web Components](./frontend-with-web-components.md) shows the supported front-end workflow for `.wc.ts` files, `serve --dev`, and legacy-project upgrades.
- [Request Data and Validation](./request-data-and-validation.md) shows how to keep transport concerns at the edge with DTOs and pipes.
- [Authentication and OAuth In Depth](./authentication-and-oauth.md) goes deeper into strategy selection, route ownership, and provider-backed login flows.
- [API Docs and Clients](./api-docs-and-clients.md) covers `/docs`, `/openapi.json`, Postman export, and the TypeScript client generator.
- [Pages, Components, HTMX, and Web Components](./pages-and-components.md) covers server-rendered UI patterns and the new Web Components workflow.
- [Data and ORM](./data-and-orm.md) is the ORM map for both standalone package users and full Assegai apps.

### Data and ORM

- [Getting Started](./orm-getting-started.md) shows the first working ORM path both inside Assegai and in standalone PHP.
- [Data Sources](./orm-data-sources.md) explains the `data_source` concept, named stores, `driver:name` resolution, and direct `DataSource` usage.
- [Entities](./orm-entities.md) explains how entity classes model persisted shape and how they differ from DTOs.
- [Relations](./orm-relations.md) covers ownership, loading, and relation writes for `OneToOne`, `ManyToOne`, `OneToMany`, and `ManyToMany`.
- [Migrations](./orm-migrations.md) covers repeatable schema change in both standalone and Assegai workflows.
- [Working with Entity Manager](./orm-entity-manager.md) explains the central ORM coordination layer beneath repositories.
- [Query Builder](./orm-query-builder.md) introduces the lower-level SQL-family builder and the dialect-specific fluent branches.
- [Drivers](./orm-drivers.md) explains the supported SQL families, their config shape, and the matching PDO extensions.
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
- `assegaiphp/auth` for session, JWT, and provider-backed OAuth authentication strategies that you can wire into your own login flow
- `assegaiphp/events` for in-process domain events and listener registration
- `assegaiphp/orm` for entity mapping and repository-backed data access
- `assegaiphp/validation` for DTO validation attributes
- `assegaiphp/forms` for form handling
- `assegaiphp/rabbitmq` and `assegaiphp/beanstalkd` for queue-backed background work
- `assegaiphp/util`, `assegaiphp/common`, and `assegaiphp/collections` as supporting libraries

## ORM track

If your app is data-backed, the ORM reading path is:

1. [Data and ORM](./data-and-orm.md)
2. [Getting Started](./orm-getting-started.md)
3. [Data Sources](./orm-data-sources.md)
4. [Entities](./orm-entities.md)
5. [Relations](./orm-relations.md)
6. [Migrations](./orm-migrations.md)
7. [Working with Entity Manager](./orm-entity-manager.md)
8. [Query Builder](./orm-query-builder.md)
9. [Drivers](./orm-drivers.md)

## Notes on accuracy

The writing here intentionally prefers verified behavior over broad claims. Where a feature is scaffolded but still benefits from a manual follow-up step, the guide says so directly.
