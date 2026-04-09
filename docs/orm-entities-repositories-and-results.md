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

## Use enums when the data has a fixed set of states

Enums are a good fit for columns such as:

- order status
- user role
- payment provider
- publication state

Start with a PHP enum:

```php
<?php

namespace Assegaiphp\BlogApi\Posts\Enums;

enum PostStatus: string
{
  case DRAFT = 'draft';
  case REVIEW = 'review';
  case PUBLISHED = 'published';
}
```

Then store the enum's backed value in the entity column:

```php
<?php

namespace Assegaiphp\BlogApi\Posts\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Queries\Sql\ColumnType;
use Assegai\Orm\Traits\ChangeRecorderTrait;
use Assegaiphp\BlogApi\Posts\Enums\PostStatus;

#[Entity(table: 'posts', database: 'blog')]
class PostEntity
{
  use ChangeRecorderTrait;

  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $title = '';

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $status = PostStatus::DRAFT->value;
}
```

That is the safest current pattern because the database stores plain strings, which are easy to query and easy to migrate.

In your service or DTO mapping code, convert between the enum and the stored value explicitly:

```php
<?php

use Assegaiphp\BlogApi\Posts\Enums\PostStatus;

public function publish(int $id): void
{
  $result = $this->postsRepository->update(
    ['id' => $id],
    (object) ['status' => PostStatus::PUBLISHED->value],
  );

  if ($result->isError()) {
    throw new RuntimeException('Failed to publish post.', previous: $result->getErrors()[0]);
  }
}

public function getStatusLabel(array $post): string
{
  return PostStatus::from($post['status'])->name;
}
```

Two practical rules help here:

- keep the entity column as the backed scalar value, not the enum object itself
- keep enum conversion close to your service or DTO boundary so the persistence format stays obvious

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

`create()` can build an entity-shaped object directly from a DTO or any other plain PHP object. For most feature code, `save()` is the best default write path.

```php
<?php

use Assegaiphp\BlogApi\Posts\DTOs\CreatePostDTO;
use RuntimeException;

public function create(CreatePostDTO $dto): object
{
  $post = $this->postsRepository->create($dto);
  $post->isPublished = false;

  $saveResult = $this->postsRepository->save($post);

  if ($saveResult->isError()) {
    throw new RuntimeException('Failed to create post.', previous: $saveResult->getErrors()[0]);
  }

  return $post;
}
```

That is usually the most natural service code in Assegai apps:

- pass the DTO straight into `create()`
- set any extra fields the DTO should not control directly
- persist the entity with `save()`

`insert()` is still available, but `save()` is the smoother day-to-day choice for most use cases because it keeps the write path consistent as relations and entity state become more involved.

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

public function findAll(): array
{
  return $this->postsRepository->find([
    'where' => ['isPublished' => true],
    'order' => ['id' => 'DESC'],
    'skip' => 0,
    'limit' => 20,
  ])->getData();
}

public function findById(int $id): object
{
  return $this->postsRepository->findOne([
    'where' => ['id' => $id],
  ])->getFirst();
}

public function countPublished(): int
{
  return $this->postsRepository->count([
    'where' => ['isPublished' => true],
  ]);
}
```

## Update records

Use `update()` when you already know the criteria. In most apps, you can pass the DTO itself instead of tediously copying fields into an array:

```php
<?php

use Assegaiphp\BlogApi\Posts\DTOs\UpdatePostDTO;
use RuntimeException;

public function updateById(int $id, UpdatePostDTO $dto): object
{
  $updateResult = $this->postsRepository->update(
    ['id' => $id],
    $dto,
  );

  if ($updateResult->isError()) {
    throw new RuntimeException('Failed to update post.', previous: $updateResult->getErrors()[0]);
  }

  return $this->postsRepository->findOne([
    'where' => ['id' => $id],
  ])->getFirst();
}
```

Use `save()` when you are working with an entity object and want insert-versus-update behavior to be decided by the entity state.

## Delete records

The recommended default is a soft delete, because entities already ship with `ChangeRecorderTrait` and the ORM supports that flow out of the box.

```php
<?php

use Assegai\Orm\Management\Options\RemoveOptions;
use RuntimeException;

public function deleteById(int $id): object
{
  $post = $this->postsRepository->findOne([
    'where' => ['id' => $id],
  ])->getFirst();

  $removeResult = $this->postsRepository->softRemove(
    $post,
    new RemoveOptions(),
  );

  if ($removeResult->isError()) {
    throw new RuntimeException('Failed to delete post.', previous: $removeResult->getErrors()[0]);
  }

  return $post;
}
```

Use a hard delete only when you are sure the row should be physically removed. The repository also exposes `remove()`, `delete()`, and `restore()` when your workflow needs them.

## Understand the result objects

The ORM returns specialized result types instead of raw arrays. Those result objects are most useful inside repositories and services, where you need access to things like errors, totals, and raw database metadata.

At the application boundary, most services read more naturally if they unwrap those results and return plain objects or arrays.

The main result types are:

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

## Unwrapping ORM results before controllers

Controllers can stay very thin because the service can unwrap repository results before they reach HTTP:

```php
<?php

namespace Assegaiphp\BlogApi\Posts;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Param;
use Assegaiphp\BlogApi\Posts\DTOs\CreatePostDTO;

#[Controller('posts')]
readonly class PostsController
{
  public function __construct(private PostsService $postsService)
  {
  }

  #[Get]
  public function findAll(): array
  {
    return $this->postsService->findAll();
  }

  #[Get(':id<int>')]
  public function findById(#[Param('id')] int $id): object
  {
    return $this->postsService->findById($id);
  }

  #[Post]
  public function create(#[Body] CreatePostDTO $dto): object
  {
    return $this->postsService->create($dto);
  }
}
```

That lets the transport layer remain simple while the repository keeps the persistence logic.

## Techniques that scale well

- Keep DTOs, entities, and services as separate responsibilities even when the feature feels small.
- Prefer module-level `data_source` config over repeating the database name on every entity, and prefer the fully qualified `driver:name` format there.
- Use `findOne()` with an explicit `where` even for primary-key lookups. It keeps the service intent obvious.
- Reach for explicit relation loading instead of assuming a property is already hydrated.

## Next step

If your model has real relationships, continue with [ORM Relations](./orm-relations.md).
