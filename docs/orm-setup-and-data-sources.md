# ORM Setup and Data Sources

This guide helps you get from "I have a database" to "my feature can inject a repository" without hand-building database connections in every service.

It is about wiring the ORM into an Assegai app cleanly:

- installing the package
- configuring databases with the CLI
- choosing where a feature gets its default data source
- injecting repositories without hand-building connections in every service

## Install the ORM

For an existing project:

```bash
composer require assegaiphp/orm
```

For a new project, the usual path is to choose database setup during `assegai new` and let the CLI scaffold the config for you.

## Configure a database with the CLI

If you skipped database setup during project creation, you can add it later:

```bash
assegai database:configure blog --mysql
assegai database:setup blog --mysql
assegai migration:setup blog --mysql
```

Common combinations:

- `--mysql` for a long-running app backed by MySQL
- `--pgsql` for PostgreSQL
- `--sqlite` for local development, tests, prototypes, and smaller apps

`database:configure` writes connection details into `config/default.php`. `database:setup` prepares the database itself. `migration:setup` creates the migrations workspace for that connection.

## What the config looks like

The generated config template already contains the database sections the ORM expects:

```php
<?php

return [
  'databases' => [
    'mysql' => [
      'blog' => [
        'host' => '127.0.0.1',
        'user' => 'root',
        'password' => '',
        'port' => 3306,
      ],
    ],
    'sqlite' => [
      'local' => [
        'path' => '.data/local.sq3',
      ],
    ],
  ],
];
```

For SQLite, the `path` is relative to the project root unless you choose one of the in-memory modes through the CLI.

## Choose where a feature gets its data source

Assegai gives you three useful levels for this choice.

### App-wide default

If most of the app uses one connection, the root module can carry that default:

```php
<?php

namespace Assegaiphp\BlogApi;

use Assegai\Core\Attributes\Modules\Module;
use Assegaiphp\BlogApi\Posts\PostsModule;

#[Module(
  imports: [PostsModule::class],
  config: ['data_source' => 'blog'],
)]
class AppModule
{
}
```

### Module-level default

This is usually the sweet spot for real applications:

```php
<?php

namespace Assegaiphp\BlogApi\Posts;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [PostsService::class],
  controllers: [PostsController::class],
  config: ['data_source' => 'blog'],
)]
class PostsModule
{
}
```

That keeps the choice close to the feature without repeating it on every entity.

### Entity-level override

If a particular entity must always live on a specific connection, put it directly on the entity:

```php
<?php

namespace Assegaiphp\BlogApi\Posts\Entities;

use Assegai\Orm\Attributes\Entity;

#[Entity(
  table: 'posts',
  database: 'blog',
)]
class PostEntity
{
}
```

You can also set the driver explicitly on the entity when that helps clarify the target:

```php
<?php

use Assegai\Orm\Enumerations\DataSourceType;

#[Entity(
  table: 'notes',
  database: 'local',
  driver: DataSourceType::SQLITE,
)]
class NoteEntity
{
}
```

## How repository injection resolves the connection

`#[InjectRepository(SomeEntity::class)]` resolves the data source in this order:

1. the entity's `database` value
2. the active module config key `data_source`
3. an error if neither is available

If the configured data source string includes a driver prefix like `sqlite:local`, the driver is derived from that prefix.

## Inject a repository into a service

This is the standard application-level pattern:

```php
<?php

namespace Assegaiphp\BlogApi\Posts;

use Assegai\Core\Attributes\Injectable;
use Assegai\Orm\Attributes\InjectRepository;
use Assegai\Orm\Management\Repository;
use Assegaiphp\BlogApi\Posts\Entities\PostEntity;

#[Injectable]
class PostsService
{
  public function __construct(
    #[InjectRepository(PostEntity::class)]
    private Repository $postsRepository,
  ) {
  }
}
```

That keeps the service focused on application behavior instead of connection management.

## Use `DataSource` directly when you really need to

Direct `DataSource` usage is still useful for scripts, tests, tooling, or low-level debugging:

```php
<?php

use App\Entities\NoteEntity;
use Assegai\Orm\DataSource\DataSource;
use Assegai\Orm\DataSource\DataSourceOptions;
use Assegai\Orm\Enumerations\DataSourceType;

$dataSource = new DataSource(new DataSourceOptions(
  entities: [NoteEntity::class],
  name: 'local',
  type: DataSourceType::SQLITE,
));

$notes = $dataSource->getRepository(NoteEntity::class);
```

Inside an Assegai module, repository injection is still the preferred path.

## Next steps

Once the data source is settled, move on to:

1. [ORM Entities, Repositories, and Results](./orm-entities-repositories-and-results.md)
2. [ORM Relations](./orm-relations.md)
3. [ORM Migrations and Database Workflows](./orm-migrations-and-database-workflows.md)
