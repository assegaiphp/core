# ORM Migrations and Database Workflows

Once more than one environment depends on your schema, database changes need a workflow you can repeat safely.

In Assegai, that usually means:

- configuring the data source
- setting up migrations for that connection
- creating migration files as the model evolves
- running the migration commands in a controlled order

## Basic command flow

For a MySQL-backed feature named `blog`:

```bash
assegai database:configure blog --mysql
assegai database:setup blog --mysql
assegai migration:setup blog --mysql
assegai migration:create create_posts_table
assegai migration:up blog --mysql
```

That sequence does four different jobs:

- `database:configure` writes the connection details to `config/default.php`
- `database:setup` prepares the database itself
- `migration:setup` creates the migration workspace for that connection
- `migration:create` adds a new migration file
- `migration:up` runs pending migrations

## The commands you will actually use

Most teams will reach for this small set repeatedly:

- `assegai migration:create <name>`
- `assegai migration:up <name> --mysql`
- `assegai migration:down <name> --mysql`
- `assegai migration:redo <name> --mysql`
- `assegai migration:refresh <name> --mysql`
- `assegai migration:list <name> --mysql`

Useful adjacent commands:

- `assegai database:load`
- `assegai database:seed`

## A practical workflow

The healthiest rhythm is usually:

1. update the entity model
2. create a migration that makes the matching schema change
3. run the migration locally
4. verify the feature through the service or controller
5. commit the entity and migration changes together

That keeps the code and schema moving as one unit instead of drifting apart.

## When to use migrations versus direct setup

Use `database:setup` for initial environment preparation.

Use migrations for application schema evolution after that:

- creating tables
- renaming columns
- adding indexes
- altering nullability
- changing relation tables or foreign keys

If the app already has data that matters, migrations should be your default path.

## Techniques that reduce upgrade pain

- Keep migration names descriptive, like `create_posts_table` or `add_profile_id_to_users`.
- Scope database defaults at the module level when possible so the migration target stays obvious.
- Land relation changes with the relation model and migration in the same change set.
- Prefer small, reversible migrations over giant one-shot rewrites.
- Use `migration:list` before and after running changes when you want a quick sanity check on state.

## Relation-specific reminder

Relation changes nearly always mean schema changes too:

- a `OneToOne` owner usually means a new foreign key column
- a `ManyToOne` often means a new foreign key on the child table
- a `ManyToMany` usually means a new join table

That is why the relation guide and the migration workflow belong together.

## How this fits with the rest of the ORM track

- [ORM Setup and Data Sources](./orm-setup-and-data-sources.md) gets the connection right
- [ORM Entities, Repositories, and Results](./orm-entities-repositories-and-results.md) gets the model and service layer right
- [ORM Relations](./orm-relations.md) gets ownership and loading right

With those in place, migrations become much easier to reason about because the schema intent is already clear in the code.
