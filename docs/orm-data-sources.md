# Data Sources

A data source is the configured place your ORM work reads from and writes to.

That sounds simple, but it is an important idea because it combines several pieces:

- the driver family, such as `mysql`, `pgsql`, `sqlite`, or `mssql`
- the connection details, such as host, port, credentials, or file path
- the name the rest of the app uses to refer to that store
- the connection object you work with at runtime

In other words, a data source is more than a database name. It is the full configured persistence endpoint.

## Why the docs say `data_source`

In Assegai module config, the key is `data_source`, not `database`.

That is intentional. The abstraction is wider than one SQL engine, so the configuration key stays broad too.

## Named data sources

The ORM expects named data sources.

That means you do not usually say connect to PostgreSQL somehow. You say connect to the named PostgreSQL data source called `cinema`.

Inside Assegai module config, that usually looks like:

```php
'data_source' => 'pgsql:cinema'
```

That string has two parts:

- `pgsql` is the driver token
- `cinema` is the configured data source name

This format is preferred because it is explicit and easy to scan.

## `OrmRuntime::configure()` versus direct `DataSource`

Use `OrmRuntime::configure()` when:

- you want to define named data sources once and reuse them
- you want helpers such as schema or migration tooling to resolve the same named config
- you want to keep connection details out of the rest of the script

Use direct `DataSource` construction when:

- you are writing a small one-file script or job
- the connection details are already known right there
- you want the shortest path to a working repository

A direct `DataSource` does **not** require `OrmRuntime::configure()` if you pass the full connection details in `DataSourceOptions`.

## Standalone configuration with `OrmRuntime`

In standalone PHP, configure named data sources through `OrmRuntime` when you want name-based resolution.

```php
<?php

use Assegai\Orm\Support\OrmRuntime;

OrmRuntime::configure([
  'databases' => [
    'mysql' => [
      'catalog' => [
        'host' => '127.0.0.1',
        'user' => 'root',
        'password' => '',
        'port' => 3306,
      ],
    ],
    'pgsql' => [
      'analytics' => [
        'host' => '127.0.0.1',
        'user' => 'postgres',
        'password' => 'secret',
        'port' => 5432,
      ],
    ],
    'sqlite' => [
      'local' => [
        'path' => __DIR__ . '/local.sqlite',
      ],
    ],
    'mssql' => [
      'reporting' => [
        'host' => '127.0.0.1',
        'user' => 'sa',
        'password' => 'secret',
        'port' => 1433,
      ],
    ],
  ],
]);
```

How to read this:

- `mysql`, `pgsql`, `sqlite`, and `mssql` group data sources by driver family
- each nested key, such as `catalog` or `reporting`, is a named data source
- the value contains the connection details that driver needs

## Creating a `DataSource` directly

If you already know the connection details, you can create a `DataSource` object directly and skip runtime configuration.

### SQLite example

```php
<?php

use App\Entities\MovieEntity;
use Assegai\Orm\DataSource\DataSource;
use Assegai\Orm\DataSource\DataSourceOptions;
use Assegai\Orm\Enumerations\DataSourceType;

$dataSource = new DataSource(new DataSourceOptions(
  entities: [MovieEntity::class],
  name: 'catalog',
  type: DataSourceType::SQLITE,
  path: __DIR__ . '/catalog.sqlite',
));
```

### MSSQL example

```php
<?php

use App\Entities\MovieEntity;
use Assegai\Orm\DataSource\DataSource;
use Assegai\Orm\DataSource\DataSourceOptions;
use Assegai\Orm\Enumerations\DataSourceType;

$dataSource = new DataSource(new DataSourceOptions(
  entities: [MovieEntity::class],
  name: 'reporting',
  type: DataSourceType::MSSQL,
  host: '127.0.0.1',
  port: 1433,
  username: 'sa',
  password: 'secret',
));
```

What each option means:

- `entities` lists the entity classes this data source should know about
- `name` is the data source name
- `type` is the driver family
- `path` is the SQLite database file path when you are using SQLite
- `host`, `port`, `username`, and `password` are the network connection details for MySQL, MariaDB, PostgreSQL, or MSSQL

## Creating a `DataSource` from named runtime config

If you have already called `OrmRuntime::configure()`, you can let the runtime supply the missing details.

```php
<?php

use App\Entities\MovieEntity;
use Assegai\Orm\DataSource\DataSource;
use Assegai\Orm\DataSource\DataSourceOptions;
use Assegai\Orm\Enumerations\DataSourceType;

$dataSource = new DataSource(new DataSourceOptions(
  entities: [MovieEntity::class],
  name: 'local',
  type: DataSourceType::SQLITE,
));
```

In that version:

- `name` and `type` identify the configured data source
- `resolveOptions(...)` inside `DataSource` merges in the configured path or credentials
- the script no longer has to repeat the same connection details everywhere

The same idea works for a named MSSQL source:

```php
<?php

$dataSource = new DataSource(new DataSourceOptions(
  entities: [MovieEntity::class],
  name: 'reporting',
  type: DataSourceType::MSSQL,
));
```

## Using a data source directly

Once a `DataSource` exists, you can work through:

- repositories via `$dataSource->getRepository(...)`
- the entity manager via `$dataSource->manager`
- the underlying client via `$dataSource->getClient()`

Example:

```php
<?php

$movies = $dataSource->getRepository(MovieEntity::class);
$entityManager = $dataSource->manager;
$pdo = $dataSource->getClient();
```

Use the highest-level tool that fits the job:

- repository for entity-scoped app work
- entity manager for broader persistence workflows
- raw client or query builder for lower-level SQL work

## Using data sources inside Assegai

In an Assegai app, the most common choice is to let the framework resolve the data source for you.

### Module-level default

This is usually the best default for real features:

```php
<?php

namespace Assegaiphp\CinemaHub\Reports;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [ReportsService::class],
  controllers: [ReportsController::class],
  config: ['data_source' => 'mssql:reporting'],
)]
class ReportsModule
{
}
```

### App-wide default

If most of the app uses one data source, the root module can provide it:

```php
<?php

#[Module(
  imports: [MoviesModule::class, ReportsModule::class],
  config: ['data_source' => 'mssql:reporting'],
)]
class AppModule
{
}
```

### Entity-level override

If one entity must always use a specific data source, put that selection on the entity and keep SQL-only storage concerns separate:

```php
<?php

use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Enumerations\DataSourceType;

#[Entity(
  table: 'audit_logs',
  dataSource: 'analytics',
  driver: DataSourceType::POSTGRESQL,
)]
class AuditLogEntity
{
}
```

## Resolution order in Assegai

When `#[InjectRepository(SomeEntity::class)]` is used inside an Assegai app, the ORM resolves the connection in this order:

1. the entity's `dataSource` value
2. the current module's `data_source`
3. an error if neither is available

That is why module-level defaults are so useful. They remove repetition while keeping the chosen data source obvious.

## Practical advice

- Prefer named data sources over ad-hoc connection setup scattered through services.
- Prefer module-level `data_source` defaults unless one entity truly needs a different store.
- Use the fully qualified `driver:name` form in Assegai config.
- Keep standalone configuration in one place when several scripts or helpers need to share it.
- Use direct `DataSource` construction for very small scripts when repeating the connection details is acceptable.
- Treat data sources as application boundaries, not just plumbing.

## Next steps

Once the connection side is clear, move on to [Entities](./orm-entities.md).
