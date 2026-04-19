# Data and ORM

The ORM gives you a few layers to work with persisted data:

- entities model stored shape
- repositories handle entity-scoped CRUD work
- the entity manager coordinates broader persistence workflows
- the query builder gives you lower-level SQL-family control when you need it

In Assegai, modules point to a `data_source` rather than a `database`. That wording is deliberate. The abstraction is meant to stay broader than one SQL engine or one storage style. Today the first-class family is SQL:

- MySQL
- MariaDB
- PostgreSQL
- SQLite

Over time, the same top-level idea is meant to grow into additional backends too. That is why these guides talk about entities, data sources, and drivers instead of collapsing everything into database config.

## What this section teaches

By the end of this track, you should understand:

- what a data source is and why the framework uses that term
- how to configure the ORM in a standalone project or an Assegai app
- how entities map PHP classes to stored records
- how relations model ownership and navigation between entities
- how migrations keep schema changes deliberate and repeatable
- when to use repositories, when to use the entity manager, and when to drop to the query builder
- how driver choice affects configuration and query fluency

## Recommended reading path

Read these in order if you are new to the ORM:

1. [Getting Started](./orm-getting-started.md)
2. [Data Sources](./orm-data-sources.md)
3. [Entities](./orm-entities.md)
4. [Relations](./orm-relations.md)
5. [Migrations](./orm-migrations.md)
6. [Working with Entity Manager](./orm-entity-manager.md)
7. [Query Builder](./orm-query-builder.md)
8. [Drivers](./orm-drivers.md)

## Two valid ways to start

There is no single correct starting point for every team.

If you are building an Assegai app, the smoothest route is usually:

1. `assegai add orm`
2. `assegai database:configure ...`
3. `assegai database:setup ...`
4. set a module-level `data_source`
5. inject a repository into a service

If you are using the ORM without Assegai, the smoothest route is usually:

1. `composer require assegaiphp/orm`
2. enable only the PDO extension for the driver you want
3. configure `OrmRuntime`
4. create a `DataSource`
5. fetch a repository or work through the entity manager

Both are first-class workflows. The difference is mostly where configuration and convenience come from.

## A mental model that holds up

If the ORM feels abstract at first, this model usually helps:

- a **driver** knows how to speak to a specific backend family such as MySQL or PostgreSQL
- a **data source** is a named configured store that uses one of those drivers
- an **entity** describes how a PHP class maps to stored data
- a **repository** is the everyday entity-scoped API for CRUD-style work
- the **entity manager** is the broader coordination layer underneath repositories
- the **query builder** is the lower-level fluent API for SQL-family work when repository options are not enough
- **migrations** capture schema changes so environments do not drift apart

## Where to go next

If you want the fastest route to a working feature, start with [Getting Started](./orm-getting-started.md).

If you already know you need to reason about configuration first, jump straight to [Data Sources](./orm-data-sources.md).
