# Migrations

Migrations are the repeatable history of schema change.

Without them, entity files and real databases drift apart quietly:

- one developer adds a column locally
- another environment never gets it
- production still has the old shape
- the code now assumes a schema that only exists on one machine

Migrations solve that by making schema change explicit, reviewable, and reversible.

## Migration files

Use this layout:

```text
migrations/
  pgsql/
    cinema/
      20260412103000_create_movies_table/
        up.sql
        down.sql
```

Each migration lives in its own directory:

- `up.sql` moves the schema forward
- `down.sql` rolls the schema back

This keeps each migration easy to review and easy to roll back.

Inside an Assegai app, prefer `assegai migration:create` to create this structure for you.

## A concrete example

If you want a `movies` table, the migration could look like this.

`up.sql`

```sql
CREATE TABLE IF NOT EXISTS movies (
  id INTEGER PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  synopsis TEXT NULL,
  release_date DATE NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL
);
```

`down.sql`

```sql
DROP TABLE IF EXISTS movies;
```

The forward change and rollback live side by side in one migration directory.

## Standalone workflow

If you are using `assegaiphp/orm` on its own, use the same migration shape.

Create a directory such as:

```text
migrations/sqlite/local/20260412103000_create_movies_table/
```

Then place your SQL in:

- `migrations/sqlite/local/20260412103000_create_movies_table/up.sql`
- `migrations/sqlite/local/20260412103000_create_movies_table/down.sql`

A small one-off script can apply the SQL through your `DataSource`:

```php
<?php

use Assegai\Orm\DataSource\DataSource;
use Assegai\Orm\DataSource\DataSourceOptions;
use Assegai\Orm\Enumerations\DataSourceType;

$dataSource = new DataSource(new DataSourceOptions(
  name: 'local',
  type: DataSourceType::SQLITE,
  path: __DIR__ . '/storage/local.sqlite',
));

$upSql = file_get_contents(
  __DIR__ . '/migrations/sqlite/local/20260412103000_create_movies_table/up.sql'
);

$dataSource->getClient()->exec($upSql ?: '');
```

For a real project, you will usually want a small runner that:

- finds migration directories in timestamp order
- executes each `up.sql`
- records which migrations have already run
- can later read `down.sql` for rollback

Use the same layout whether the project is a full Assegai app or a plain PHP script.

## Assegai workflow

Inside an Assegai app, the CLI is the preferred way to work with migrations across MySQL, MariaDB, PostgreSQL, SQLite, and MSSQL.

### Create the files with the CLI

Use `migration:create` instead of creating the directory and files by hand:

```bash
assegai migration:create create_movies_table --pgsql --database=cinema
```

This creates the timestamped migration directory and both SQL files for you:

- `up.sql`
- `down.sql`

That saves time and avoids small mistakes in driver folders, database names, timestamps, and file names.

### Typical command flow

```bash
assegai database:configure cinema --pgsql
assegai database:setup cinema --pgsql
assegai migration:setup cinema --pgsql
assegai migration:create create_movies_table --pgsql --database=cinema
assegai migration:up cinema --pgsql
```

What each command is responsible for:

- `database:configure` writes the connection details into app config
- `database:setup` creates the database if needed and the driver permits it
- `migration:setup` prepares the migration workspace
- `migration:create` creates a migration directory with `up.sql` and `down.sql`
- `migration:up` runs pending migrations

Useful related commands:

- `assegai migration:down`
- `assegai migration:redo`
- `assegai migration:refresh`
- `assegai migration:list`

## The healthy workflow

The safest rhythm is usually:

1. change the entity model
2. create a migration that matches the model change
3. inside an Assegai app, use `assegai migration:create`; otherwise create the files by hand
4. write the SQL in `up.sql` and `down.sql`
5. run the migration locally
6. verify the feature through the repository or service layer
7. commit the entity and migration together

That keeps code and schema moving as one unit instead of drifting apart.

## When to use migrations and when not to

Use migrations for schema evolution:

- creating new tables
- adding or dropping columns
- renaming tables
- changing nullability
- adjusting relation tables and foreign keys

Use `database:setup` or first-run bootstrap for initial environment preparation.

Once the app has real shared environments, schema changes should move through migrations by default.

## Manual workflow

You can still write migrations by hand.

That means:

- handwritten SQL migrations remain fully supported
- generated migrations are meant to help, not trap you
- future higher-level sync tooling should reduce friction, not take away control

## Practical advice

- Give migrations descriptive names such as `create_movies_table` or `add_genre_id_to_showtimes`.
- Prefer small reversible changes over one giant migration that does everything at once.
- Keep relation changes and their schema changes together.
- Check `migration:list` when you want a quick sanity check on state.

## Next steps

Once migrations feel clear, deepen the data layer with:

1. [Working with Entity Manager](./orm-entity-manager.md)
2. [Query Builder](./orm-query-builder.md)
