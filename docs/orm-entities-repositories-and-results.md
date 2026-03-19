# ORM Entities, Repositories, and Results

Use this guide once you have a resource and want to persist real data.

It covers the everyday ORM work:

- modeling entities
- injecting repositories
- reading and writing records
- understanding the result objects that come back

## Start from a generated resource

`assegai g r posts` gives you a practical starting shape:

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

That structure already separates the main concerns cleanly:

- DTOs shape request data
- the entity shapes persistence
- the service uses the repository
- the controller handles HTTP

## Model an entity deliberately

The generated entity gives you an id. The next step is to define the real columns:

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

  #[Column(type: ColumnType::TEXT, nullable: false)]
  public string $body = '';

  #[Column(type: ColumnType::BOOLEAN, nullable: false)]
  public bool $isPublished = false;
}
```

Two habits are worth keeping:

- put request validation in DTOs, not in entity classes
- keep the entity close to the actual table shape

## Inject the repository into the service

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

That repository is the main API for day-to-day data access.

## Create records

`create()` builds an entity-shaped object. `insert()` persists it.

```php
<?php

use Assegai\Orm\Queries\QueryBuilder\Results\InsertResult;
use Assegaiphp\BlogApi\Posts\DTOs\CreatePostDTO;

public function create(CreatePostDTO $dto): InsertResult
{
  $post = $this->postsRepository->create([
    'title' => $dto->title,
    'body' => $dto->body,
    'isPublished' => false,
  ]);

  return $this->postsRepository->insert($post);
}
```

If you are inserting an entity graph that includes owner-side relations, prefer `save()` with `InsertOptions`. The relation guide covers that in detail.

## Read records

The most common query entry points are:

- `find()` for a list
- `findOne()` for one record
- `findBy()` for simple where clauses
- `count()` for totals
- `findAndCount()` when you want entities plus a total

Example service methods:

```php
<?php

use Assegai\Orm\Queries\QueryBuilder\Results\FindResult;

public function findAll(): FindResult
{
  return $this->postsRepository->find([
    'where' => ['isPublished' => true],
    'order' => ['id' => 'DESC'],
    'skip' => 0,
    'limit' => 20,
  ]);
}

public function findById(int $id): FindResult
{
  return $this->postsRepository->findOne([
    'where' => ['id' => $id],
  ]);
}

public function countPublished(): int
{
  return $this->postsRepository->count([
    'where' => ['isPublished' => true],
  ]);
}
```

## Update records

Use `update()` when you already know the criteria:

```php
<?php

use Assegai\Orm\Queries\QueryBuilder\Results\UpdateResult;
use Assegaiphp\BlogApi\Posts\DTOs\UpdatePostDTO;

public function updateById(int $id, UpdatePostDTO $dto): UpdateResult
{
  return $this->postsRepository->update(
    ['id' => $id],
    [
      'title' => $dto->title,
      'body' => $dto->body,
    ],
  );
}
```

Use `save()` when you are working with an entity object and want insert-versus-update behavior to be decided by the entity state.

## Delete records

For direct deletes:

```php
<?php

public function deleteById(int $id)
{
  return $this->postsRepository->delete(['id' => $id]);
}
```

The repository also exposes `remove()`, `softRemove()`, and `restore()` when your workflow needs them.

## Understand the result objects

The ORM returns specialized result types instead of raw arrays:

- `FindResult`
- `InsertResult`
- `UpdateResult`
- `DeleteResult`

The most useful methods are:

- `isOk()` and `isError()`
- `getErrors()`
- `getData()`
- `getRaw()`
- `getTotalAffectedRows()`

`FindResult` also gives you:

- `getFirst()` for the first item in a list result
- `getTotal()` for the total record count
- `isEmpty()` for an easy emptiness check

## Returning ORM results from controllers

Controllers can stay very thin because core already knows how to serialize these results:

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

That lets the transport layer remain simple while the repository keeps the persistence logic.

## Techniques that scale well

- Keep DTOs, entities, and services as separate responsibilities even when the feature feels small.
- Prefer module-level `data_source` config over repeating the database name on every entity.
- Use `findOne()` with an explicit `where` even for primary-key lookups. It keeps the service intent obvious.
- Reach for explicit relation loading instead of assuming a property is already hydrated.

## Next step

If your model has real relationships, continue with [ORM Relations](./orm-relations.md).
