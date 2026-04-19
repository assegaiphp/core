# Getting Started

Start here when you want to get the ORM running quickly.

- Use the standalone path if you are writing plain PHP, a script, a worker, or a small library and want direct control over configuration.
- Use the Assegai path if you already have an app and want module-level `data_source` defaults, repository injection, and CLI help.

## Standalone quick start

### 1. Install the package

```bash
composer require assegaiphp/orm
```

### 2. Enable the driver extension you actually need

Only enable the PDO extension for the backend you plan to use:

- `pdo_mysql` for MySQL or MariaDB
- `pdo_pgsql` for PostgreSQL
- `pdo_sqlite` for SQLite
- `pdo_sqlsrv` for MSSQL

### 3. Decide how you want to configure the script

There are two valid standalone patterns.

Use `OrmRuntime::configure()` when:

- you want named data sources such as `catalog` or `analytics`
- you want to reuse the same connection definition in several places
- you want static helpers such as schema tools to resolve the same named config

Use a direct `DataSource` when:

- you are writing one small script
- you already know the exact connection details in that file
- you want the fewest moving parts possible

### 4. The simplest one-file approach: build the `DataSource` directly

If all the connection details are already known in the script, you can skip `OrmRuntime::configure()` entirely.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

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

$movies = $dataSource->getRepository(MovieEntity::class);
$entityManager = $dataSource->manager;
$pdo = $dataSource->getClient();
```

Why this works:

- `DataSourceOptions` already contains the connection details
- the ORM does not need runtime lookup for this specific data source
- the repository, entity manager, and raw PDO client all come from that one object

### 5. Use `OrmRuntime::configure()` when you want named configuration

If you want the rest of the script to refer to a named data source instead of repeating the path or credentials, configure the runtime first.

```php
<?php

use Assegai\Orm\Support\OrmRuntime;

OrmRuntime::configure([
  'databases' => [
    'sqlite' => [
      'catalog' => [
        'path' => __DIR__ . '/catalog.sqlite',
      ],
    ],
  ],
]);
```

Then the `DataSource` only needs the entity list plus the data source identity:

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
));
```

What changed here:

- `name: 'catalog'` and `type: DataSourceType::SQLITE` tell the ORM which named config to load
- the runtime fills in the missing path from the configured `sqlite.catalog` entry
- schema helpers and other named-data-source workflows can now resolve the same config too

### 6. All together: a single-file SQLite example

This example shows a full standalone script that you can use as a realistic starting point. It:

- defines an entity
- configures a named SQLite data source
- creates the table if it does not exist
- creates a repository
- inserts a row
- reads the rows back

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\DataSource\DataSource;
use Assegai\Orm\DataSource\DataSourceOptions;
use Assegai\Orm\DataSource\Schema;
use Assegai\Orm\DataSource\SchemaOptions;
use Assegai\Orm\Enumerations\DataSourceType;
use Assegai\Orm\Enumerations\SQLDialect;
use Assegai\Orm\Queries\Sql\ColumnType;
use Assegai\Orm\Support\OrmRuntime;

#[Entity(
  table: 'movies',
  database: 'catalog',
  driver: DataSourceType::SQLITE,
)]
class MovieEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $title = '';

  #[Column(type: ColumnType::TEXT, nullable: true)]
  public ?string $synopsis = null;

  #[Column(type: ColumnType::BOOLEAN, nullable: false)]
  public bool $isNowShowing = false;
}

OrmRuntime::configure([
  'databases' => [
    'sqlite' => [
      'catalog' => [
        'path' => __DIR__ . '/catalog.sqlite',
      ],
    ],
  ],
]);

Schema::createIfNotExists(
  MovieEntity::class,
  new SchemaOptions(
    dbName: 'catalog',
    dialect: SQLDialect::SQLITE,
  ),
);

$dataSource = new DataSource(new DataSourceOptions(
  entities: [MovieEntity::class],
  name: 'catalog',
  type: DataSourceType::SQLITE,
));

$movies = $dataSource->getRepository(MovieEntity::class);

$movie = $movies->create([
  'title' => 'The Lantern City',
  'synopsis' => 'A detective follows a string of disappearing film reels.',
  'isNowShowing' => true,
]);

$saveResult = $movies->save($movie);

if ($saveResult->isError()) {
  throw $saveResult->getLatestError();
}

$allMovies = $movies->find([
  'order' => ['id' => 'DESC'],
])->getData();

var_dump($allMovies);
```

If you want the shortest possible one-file script, you can remove the `OrmRuntime::configure()` block and pass `path: __DIR__ . '/catalog.sqlite'` directly into `DataSourceOptions` instead.

### 7. The same flow works for the other SQL drivers

The standalone shape does not change when you move to MySQL, MariaDB, PostgreSQL, or MSSQL. You swap the driver and connection details.

For example, a direct MSSQL data source looks like this:

```php
<?php

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

That is already enough to be productive in a standalone PHP project.

## Assegai quick start

### 1. Add the ORM to the app

```bash
assegai add orm
```

That command is the preferred starting point inside Assegai because it can:

- require `assegaiphp/orm` if it is missing
- import `OrmModule`
- expose the ORM console commands through package discovery

### 2. Configure and create a database

```bash
assegai database:configure cinema --pgsql
assegai database:setup cinema --pgsql
assegai migration:setup cinema --pgsql
```

Each command has a different job:

- `database:configure` writes connection details into app config
- `database:setup` creates the database if it does not already exist and the driver can do so
- `migration:setup` prepares the migration workspace for that named data source

Use the matching driver flag for your actual backend. The same flow applies when the app uses MySQL, MariaDB, SQLite, or MSSQL.

### 3. Give the module a default data source

```php
<?php

namespace Assegaiphp\CinemaHub\Movies;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [MoviesService::class],
  controllers: [MoviesController::class],
  config: ['data_source' => 'pgsql:cinema'],
)]
class MoviesModule
{
}
```

That `driver:name` format is the preferred module-level form:

- `pgsql` is the driver token
- `cinema` is the named configured data source

### 4. Inject a repository into a service

```php
<?php

namespace Assegaiphp\CinemaHub\Movies;

use Assegai\Core\Attributes\Injectable;
use Assegai\Orm\Attributes\InjectRepository;
use Assegai\Orm\Management\Repository;
use Assegaiphp\CinemaHub\Movies\Entities\MovieEntity;

#[Injectable]
class MoviesService
{
  public function __construct(
    #[InjectRepository(MovieEntity::class)]
    private readonly Repository $movies,
  ) {
  }

  public function all(): array
  {
    return $this->movies->find([
      'order' => ['id' => 'DESC'],
    ])->getData();
  }
}
```

This is the normal Assegai day-to-day path:

- the module decides the default data source
- the entity defines the persistence shape
- the service works through a repository instead of manually opening connections

## What to learn next

Now that you have a path that runs, move into the concepts in order:

1. [Data Sources](./orm-data-sources.md)
2. [Entities](./orm-entities.md)
3. [Relations](./orm-relations.md)
4. [Migrations](./orm-migrations.md)

If you already know you want lower-level control, continue with [Working with Entity Manager](./orm-entity-manager.md) and [Query Builder](./orm-query-builder.md).
