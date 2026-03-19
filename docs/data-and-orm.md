# Data and ORM

Think of the ORM as the part of the stack that maps PHP classes to database tables and gives your services repositories instead of hand-written SQL everywhere.

Assegai's ORM story is intentionally familiar to teams coming from NestJS and TypeORM:

- entities describe persistence shape
- repositories are injected into services
- modules can choose the default data source for a feature
- migrations evolve the schema deliberately
- relations are explicit, not magical

This page is the map for that workflow. It explains the mental model, the recommended techniques, and where to go next in the deeper ORM guides.

## The happy path

For most data-backed applications, the smoothest workflow is:

1. create a project with `assegai new`
2. configure a database during project setup or later with the CLI
3. generate a resource with `assegai g r posts`
4. turn the generated entity into a real model
5. inject a repository into the service
6. add migrations once the schema starts to matter

That path is what the CLI, `core`, and `orm` are all steering toward.

## Database support today

The data source enum surface is broader than the maturity level you should assume for day-to-day application work.

Today, the smoothest path is usually:

- MySQL first for long-running application work
- SQLite next for local development, tests, prototypes, and small apps
- PostgreSQL with support continuing to improve

Other data source types exist in the enum surface, but they should be treated as a longer-term compatibility direction rather than the default recommendation for production apps today.

## Techniques

These are the habits that keep Assegai ORM code predictable as the app grows:

- Start from a generated resource. `assegai g r posts` gives you DTOs, an entity, a module, a service, and a controller that already fit the framework's conventions.
- Prefer module-level `data_source` defaults. It usually gives the best balance between explicitness and repetition.
- Keep DTOs and entities separate. DTOs describe request shape; entities describe persistence shape.
- Load relations explicitly at the query boundary. Ask for the relation you need in `find()` or `findOne()` instead of assuming it will always be present.
- Write through the owning side of a relation. The property with `#[JoinColumn]` or `#[JoinTable]` is the side that controls the stored key.
- Let controllers stay thin. Returning `FindResult`, `InsertResult`, or `UpdateResult` directly is a normal and supported pattern.

## ORM guide map

Use this set as a progressive track:

1. [ORM Setup and Data Sources](./orm-setup-and-data-sources.md)
2. [ORM Entities, Repositories, and Results](./orm-entities-repositories-and-results.md)
3. [ORM Relations](./orm-relations.md)
4. [ORM Migrations and Database Workflows](./orm-migrations-and-database-workflows.md)

## Quick example

This is the smallest realistic flow for a generated resource:

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

From there, the deeper guides help you decide:

- how the feature chooses its data source
- how to model columns and result shapes
- how to load and persist relations
- how to evolve the schema with migrations
