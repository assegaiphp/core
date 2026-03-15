# Data and ORM

Assegai is often used together with `assegaiphp/orm`, and the CLI already leans in that direction.

The ORM model is strongly reminiscent of TypeORM:

- entities describe persistence shape
- repositories are injected into services
- migrations evolve the schema
- named data sources decide where a feature reads and writes

If you enable database setup during `assegai new`, the installer currently:

- asks which database to configure
- checks the required PHP extensions for that database
- writes connection settings into `config/default.php`
- generates a default users resource when one does not already exist
- updates `AppModule`
- attempts to install `assegaiphp/orm`

That is the intended happy path for data-backed applications.

## Database support today

The data source enum surface is broader than the maturity level you should assume for day-to-day application work.

Today, the smoothest path is usually:

- MySQL first
- SQLite next
- PostgreSQL with support continuing to improve

Other data source types exist in the enum surface, but they should be treated as a longer-term compatibility direction rather than the default recommendation for production apps today.

## Database commands you will reach for

If you want to add or adjust the database layer later, the CLI exposes:

```bash
assegai database:configure blog --mysql
assegai database:setup blog --mysql
assegai migration:setup blog --mysql
assegai migration:create create_posts_table
assegai migration:up blog --mysql
```

Useful related commands:

- `assegai database:load`
- `assegai database:seed`
- `assegai migration:list`
- `assegai migration:down`
- `assegai migration:refresh`

## Start from the generated resource

`assegai g r posts` gives you a useful structure:

```text
src/Posts/
├── DTOs/
│   ├── CreatePostDTO.php
│   └── UpdatePostDTO.php
├── Entities/
│   └── PostEntity.php
├── PostsController.php
├── PostsModule.php
└── PostsService.php
```

That is enough to sketch the full data flow:

- DTOs for input
- entity for persistence shape
- service for repository work
- controller for HTTP

## Choose a data source

There are two common ways to define the default data source a feature should use.

### App-wide default

Because the root module is still a module, you can define an app-wide default there:

```php
<?php

namespace Assegaiphp\BlogApi;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Config\ProjectConfig;
use Assegaiphp\BlogApi\Posts\PostsModule;

#[Module(
  providers: [ProjectConfig::class, AppService::class],
  controllers: [AppController::class],
  imports: [PostsModule::class],
  config: ['data_source' => 'blog'],
)]
class AppModule
{
}
```

### Feature-level default

If a feature belongs on a different connection, give that module its own default:

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

### Entity-level override

If you want the entity itself to be explicit, set the database name on the entity:

```php
#[Entity(
  table: 'posts',
  database: 'blog',
)]
class PostEntity
{
}
```

`#[InjectRepository]` resolves the data source from the entity's `database` value first, then falls back to the module config key `data_source`.

## Add a real entity

The generated entity gives you an id. The next step is to define columns and a data source name that matches your configured database.

```php
<?php

namespace Assegaiphp\BlogApi\Posts\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Queries\Sql\ColumnType;
use Assegai\Orm\Traits\ChangeRecorderTrait;

#[Entity(
  table: 'posts',
  database: 'blog',
)]
class PostEntity
{
  use ChangeRecorderTrait;

  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $title = '';

  #[Column(type: ColumnType::TEXT)]
  public string $body = '';
}
```

The `database` value should match the configured connection name in `config/default.php`.

If you prefer not to repeat that on every entity in a feature, put `config: ['data_source' => 'blog']` on the owning module and let repository injection use that as the default.

## Inject a repository into your service

The ORM exposes `#[InjectRepository]` for repository injection:

```php
<?php

namespace Assegaiphp\BlogApi\Posts;

use Assegai\Core\Attributes\Injectable;
use Assegai\Orm\Attributes\InjectRepository;
use Assegai\Orm\Management\Repository;
use Assegai\Orm\Queries\QueryBuilder\Results\FindResult;
use Assegai\Orm\Queries\QueryBuilder\Results\InsertResult;
use Assegaiphp\BlogApi\Posts\DTOs\CreatePostDTO;
use Assegaiphp\BlogApi\Posts\Entities\PostEntity;

#[Injectable]
class PostsService
{
  public function __construct(
    #[InjectRepository(PostEntity::class)]
    private Repository $postsRepository,
  ) {
  }

  public function findAll(): FindResult
  {
    return $this->postsRepository->find([
      'order' => ['id' => 'DESC'],
      'limit' => 20,
      'skip' => 0,
    ]);
  }

  public function findById(int $id): FindResult
  {
    return $this->postsRepository->findOne([
      'where' => ['id' => $id],
    ]);
  }

  public function create(CreatePostDTO $dto): InsertResult
  {
    $post = $this->postsRepository->create([
      'title' => $dto->title,
      'body' => $dto->body,
    ]);

    return $this->postsRepository->insert($post);
  }
}
```

This is where the Assegai stack starts to feel powerful:

- the controller still stays thin
- the service remains injectable
- the entity defines persistence shape
- the repository handles data access
- the data source choice can live at app, module, or entity level

## A controller can return ORM results directly

Core knows how to respond to ORM result objects automatically:

```php
<?php

namespace Assegaiphp\BlogApi\Posts;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Param;
use Assegai\Orm\Queries\QueryBuilder\Results\FindResult;
use Assegai\Orm\Queries\QueryBuilder\Results\InsertResult;
use Assegaiphp\BlogApi\Posts\DTOs\CreatePostDTO;

#[Controller('posts')]
readonly class PostsController
{
  public function __construct(private PostsService $postsService)
  {
  }

  #[Get]
  public function findAll(): FindResult
  {
    return $this->postsService->findAll();
  }

  #[Get(':id<int>')]
  public function findById(#[Param('id')] int $id): FindResult
  {
    return $this->postsService->findById($id);
  }

  #[Post]
  public function create(#[Body] CreatePostDTO $dto): InsertResult
  {
    return $this->postsService->create($dto);
  }
}
```

When these results reach the responder layer:

- `FindResult` is turned into API-style JSON with `data`, `total`, `limit`, and `skip`
- `InsertResult` and `UpdateResult` are serialized as JSON payloads

That makes the controller code nice and direct. The controller does not need to unwrap repository responses just to make them HTTP-friendly.

## Use the generator, then deepen the feature

The best ORM workflow in Assegai today is usually:

1. generate a resource with the CLI
2. fill in the entity with real columns
3. decide whether the data source belongs on the app module, the feature module, or the entity
4. inject a repository into the service
5. replace the generated placeholder strings with real query logic

This gives you the speed of scaffolding and the clarity of explicit data modeling.
