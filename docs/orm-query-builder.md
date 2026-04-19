# Query Builder

The query builder is the lower-level fluent API for SQL-family work.

Most day-to-day feature code should still start with repositories or the entity manager. The query builder becomes useful when:

- repository find options are not expressive enough
- you need precise SQL control
- you want dialect-specific features such as PostgreSQL `DISTINCT ON`, MySQL and MariaDB `ON DUPLICATE KEY UPDATE`, or SQL Server `TOP`

## The key architectural idea

The SQL query builder now has typed dialect roots.

That means:

- `switchToMysql()` returns a MySQL-flavoured builder
- `switchToMariaDb()` returns a MariaDB-flavoured builder
- `switchToPostgres()` returns a PostgreSQL-flavoured builder
- `switchToSqlite()` returns a SQLite-flavoured builder
- `switchToMsSql()` returns an MSSQL-flavoured builder

This matters because the fluent path can stay honest. Once you switch into a dialect, you can follow methods that make sense for that dialect without pretending every backend supports the same syntax.

## Creating a root query

Start from a live data source:

```php
<?php

use Assegai\Orm\Queries\Sql\SQLQuery;

$query = SQLQuery::forConnection(
  db: $dataSource->getClient(),
  dialect: $dataSource->getDialect(),
);
```

`SQLQuery::forConnection(...)` inspects the connection and gives you the right SQL-family root builder.

## A basic SELECT example

```php
<?php

$result = $query
  ->select()
  ->all(['id', 'title', 'is_now_showing'])
  ->from('movies')
  ->where(['is_now_showing' => true])
  ->orderBy(['id' => 'DESC'])
  ->limit(20)
  ->execute();

$rows = $result->getData();
```

Read that chain in order:

1. `select()` begins a `SELECT`
2. `all([...])` chooses the columns
3. `from('movies')` chooses the table
4. `where([...])` adds conditions
5. `orderBy([...])` sorts the results
6. `limit(20)` constrains the result size
7. `execute()` runs the SQL

## PostgreSQL-specific fluency

Once you switch to PostgreSQL, you can use PostgreSQL-only fluency such as `distinctOn(...)`.

```php
<?php

$latestPerCity = $query
  ->switchToPostgres()
  ->select()
  ->distinctOn(['city'])
  ->all(['city', 'name', 'created_at'])
  ->from('cinemas')
  ->orderBy([
    'city' => 'ASC',
    'created_at' => 'DESC',
  ])
  ->execute()
  ->getData();
```

That is exactly the kind of case typed dialect builders are for. `DISTINCT ON` is a PostgreSQL idea, so it belongs on the PostgreSQL branch.

## MySQL and MariaDB fluency

The MySQL family can expose methods such as `highPriority()` and `onDuplicateKeyUpdate(...)`.

```php
<?php

$query
  ->switchToMysql()
  ->select()
  ->highPriority()
  ->all(['id', 'title'])
  ->from('movies')
  ->where(['is_now_showing' => true])
  ->limit(10)
  ->execute();
```

For insert-style workflows:

```php
<?php

$query
  ->switchToMysql()
  ->insertInto('movies')
  ->singleRow(['title', 'synopsis'])
  ->values(['Harbor Lights', 'A missing reel returns to circulation.'])
  ->onDuplicateKeyUpdate([
    'synopsis = VALUES(synopsis)',
  ])
  ->execute();
```

The MariaDB root follows the same family shape:

```php
<?php

$query
  ->switchToMariaDb()
  ->insertInto('movies')
  ->singleRow(['title', 'synopsis'])
  ->values(['Evening Signal', 'A projectionist starts hearing coded broadcasts.'])
  ->onDuplicateKeyUpdate([
    'synopsis = VALUES(synopsis)',
  ])
  ->execute();
```

## MSSQL-specific fluency

The MSSQL branch can expose SQL Server ideas such as `top(...)`.

```php
<?php

$recentMovies = $query
  ->switchToMsSql()
  ->select()
  ->top(5)
  ->all(['id', 'title', 'created_at'])
  ->from('movies')
  ->orderBy(['created_at' => 'DESC'])
  ->execute()
  ->getData();
```

That keeps SQL Server-specific selection on the SQL Server branch instead of treating it as generic SQL.

## PostgreSQL delete with `RETURNING`

The PostgreSQL branch can also expose `RETURNING` on deletes:

```php
<?php

$deleted = $query
  ->switchToPostgres()
  ->deleteFrom('showtimes')
  ->where(['id' => 42])
  ->returning(['id', 'movie_id'])
  ->execute()
  ->getData();
```

That kind of fluent branch is exactly why the dialect-root design matters.

## When not to use the query builder

Do not reach for the query builder just because it feels powerful.

Prefer a repository or entity manager when:

- you are doing normal CRUD on one entity
- you want relation loading through find options
- you want the ORM to keep more of the persistence logic aligned with entity metadata

Use the query builder when the SQL itself is the important part of the task.

## Practical advice

- Start with repositories.
- Move to the entity manager when the workflow spans multiple entities.
- Drop to the query builder when SQL precision or dialect-specific fluency matters.
- Treat `switchTo...` as a real branch into a typed dialect path, not just a string toggle.

## Next steps

To understand how these branches are configured in real apps, continue with [Drivers](./orm-drivers.md).
