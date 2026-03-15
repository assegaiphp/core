# Controllers and Routing

Controllers are where Assegai meets HTTP. They are responsible for:

- route declarations
- request parameter binding
- handing work to providers
- returning JSON, views, or components

## The generated resource shape

If you run:

```bash
assegai g r posts
```

you get a controller like this:

```php
<?php

namespace Assegaiphp\BlogApi\Posts;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Put;
use Assegai\Core\Attributes\Param;
use Assegaiphp\BlogApi\Posts\DTOs\CreatePostDTO;
use Assegaiphp\BlogApi\Posts\DTOs\UpdatePostDTO;

#[Controller('posts')]
readonly class PostsController
{
  public function __construct(private PostsService $postsService)
  {
  }

  #[Get]
  public function findAll(): string
  {
    return $this->postsService->findAll();
  }

  #[Get(':id')]
  public function findById(#[Param('id')] int $id): string
  {
    return $this->postsService->findById($id);
  }

  #[Post]
  public function create(#[Body] CreatePostDTO $createPostDto): string
  {
    return $this->postsService->create($createPostDto);
  }

  #[Put(':id')]
  public function updateById(
    #[Param('id')] int $id,
    #[Body] UpdatePostDTO $updatePostDto,
  ): string {
    return $this->postsService->updateById($id, $updatePostDto);
  }

  #[Delete(':id')]
  public function deleteById(#[Param('id')] int $id): string
  {
    return $this->postsService->deleteById($id);
  }
}
```

That is the happy-path Assegai rhythm:

- the route prefix lives on the controller
- each method declares its HTTP verb
- input is bound through attributes
- the controller delegates immediately to a provider

## Supported HTTP method attributes

The core package includes:

- `#[Get]`
- `#[Post]`
- `#[Put]`
- `#[Patch]`
- `#[Delete]`
- `#[Head]`
- `#[Options]`
- `#[All]`

`#[Get]` defaults to `200`, and `#[Post]` defaults to `201`.

## Route parameters

Dynamic segments use the `:name` syntax:

```php
#[Get(':id')]
public function findById(#[Param('id')] int $id): array
{
  return ['id' => $id];
}
```

Assegai also supports constrained route params:

```php
#[Get(':id<int>')]
public function findById(#[Param('id')] int $id): array
{
  return ['id' => $id];
}

#[Get(':slug<slug>')]
public function findBySlug(#[Param('slug')] string $slug): array
{
  return ['slug' => $slug];
}
```

The built-in constraints verified in the current core test suite are:

- `int`
- `slug`
- `uuid`
- `alpha`
- `alnum`
- `hex`
- `ulid`

Use them. They make routes more self-documenting and reduce ambiguity early in the pipeline.

## Request binding decorators

The most important parameter decorators are:

- `#[Param('id')]` for route params
- `#[Body]` for request bodies
- `#[Query]` for query strings
- `#[Req]` for the request object
- `#[Res]` for the response object

### Route params

```php
#[Get(':id<int>')]
public function show(#[Param('id')] int $id): array
{
  return ['id' => $id];
}
```

### Query strings

```php
<?php

use Assegai\Core\Attributes\Http\Query;
use Assegai\Core\Http\Requests\RequestQuery;

#[Get]
public function index(#[Query] RequestQuery $query): array
{
  return [
    'search' => $query->get('search'),
    'limit' => $query->get('limit', '10'),
  ];
}
```

### Request bodies

```php
#[Post]
public function create(#[Body] CreatePostDTO $dto): string
{
  return $this->postsService->create($dto);
}
```

Because generated DTOs are marked `#[Injectable]`, they fit naturally into the body-binding and DI flow.

## Request and response access

You do not have to reach for the raw request often, but it is there when you need it:

```php
<?php

use Assegai\Core\Attributes\Req;
use Assegai\Core\Http\Requests\Request;

#[Get('meta')]
public function meta(#[Req] Request $request): array
{
  return [
    'method' => $request->getMethod()->value,
    'path' => $request->getPath(),
    'limit' => $request->getLimit(),
    'skip' => $request->getSkip(),
  ];
}
```

## Nested routing through modules

Assegai's route tree is shaped by module composition.

If your root module imports `UsersModule`, `PostsModule`, and `AboutModule`, each feature brings its own controller prefix with it:

- `UsersController` with `#[Controller('users')]` lives at `/users`
- `PostsController` with `#[Controller('posts')]` lives at `/posts`
- `AboutController` with `#[Controller('about')]` lives at `/about`

That matters because the route structure stays aligned with the code structure. You do not need a second routing registry to understand where things live.

## Generate nested route branches from the CLI

The CLI can build nested modules for you directly from the resource path.

For example:

```bash
assegai g r api
assegai g r api/posts
```

That gives you:

```text
src/Api/
├── ApiController.php
├── ApiModule.php
├── ApiService.php
├── DTOs/
│   ├── CreateApiDTO.php
│   └── UpdateApiDTO.php
├── Entities/
│   └── ApiEntity.php
└── Posts/
    ├── DTOs/
    │   ├── CreatePostDTO.php
    │   └── UpdatePostDTO.php
    ├── Entities/
    │   └── PostEntity.php
    ├── PostsController.php
    ├── PostsModule.php
    └── PostsService.php
```

And the CLI updates the module graph for you:

- `AppModule` imports `ApiModule`
- `ApiModule` imports `PostsModule`

Because the generated controllers use `#[Controller('api')]` and `#[Controller('posts')]`, the resulting branch is:

- `GET /api`
- `GET /api/:id`
- `GET /api/posts`
- `GET /api/posts/:id`

That pattern scales nicely when you want a dedicated API area without losing the feature-module structure.

## Guards and interceptors

Current core behavior clearly supports guards and interceptors at controller and handler level.

### Guard example

```php
<?php

use Assegai\Core\Attributes\UseGuards;
use Assegai\Core\Interfaces\ICanActivate;
use Assegai\Core\Interfaces\IExecutionContext;

class AdminGuard implements ICanActivate
{
  public function canActivate(IExecutionContext $context): bool
  {
    return true;
  }
}

#[UseGuards(AdminGuard::class)]
#[Get('admin')]
public function adminOnly(): array
{
  return ['ok' => true];
}
```

### Interceptor example

```php
<?php

use Assegai\Core\Attributes\UseInterceptors;
use Assegai\Core\Interceptors\EmptyResultInterceptor;

#[UseInterceptors(EmptyResultInterceptor::class)]
#[Get(':id')]
public function maybeFindOne(#[Param('id')] int $id): array
{
  return [];
}
```

Use guards for access decisions and interceptors for cross-cutting behavior around handler execution.

For a deeper walkthrough, see [Guards, Interceptors, Pipes, and Middleware](./guards-interceptors-pipes-and-middleware.md).

## Practical routing advice

A good default approach is:

- use one controller prefix per feature
- keep handlers thin
- use constrained params instead of parsing everything manually
- pass work to providers quickly
- return domain results and let responders do their job

That keeps controllers expressive without turning them into a second service layer.
