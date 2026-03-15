# Assegai Guides

These guides are written for AssegaiPHP as a whole, not just `assegaiphp/core`.

They are based on:

- the current `assegai` CLI installed in this environment
- a freshly scaffolded Assegai app
- generated `resource` and `page` schematics
- the `assegaiphp/core`, `assegaiphp/orm`, and `assegaiphp/validation` packages available locally
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
4. [Controllers and Routing](./controllers-and-routing.md)
5. [Modules and Providers](./modules-and-providers.md)
6. [Pages and Components](./pages-and-components.md)
7. [Guards, Interceptors, Pipes, and Middleware](./guards-interceptors-pipes-and-middleware.md)
8. [Data and ORM](./data-and-orm.md)
9. [Queues and Background Jobs](./queues-and-background-jobs.md)
10. [Request Data and Validation](./request-data-and-validation.md)

## What makes Assegai feel fast

The shortest happy path looks like this:

```bash
assegai new blog-api
cd blog-api
assegai serve
assegai g r posts
assegai g pg about
```

From there you already have:

- a running app
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
- declarations and components shape rendered pages
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

## Notes on accuracy

These docs intentionally prefer verified behavior over broad claims. Where a feature is scaffolded but still benefits from a manual follow-up step, the guide says so directly.
