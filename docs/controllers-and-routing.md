# Controllers and Routing

Controllers are where Assegai turns an incoming HTTP request into application work.

They are responsible for:

- declaring route prefixes and handlers
- binding route, query, body, file, and host data to method parameters
- delegating work to providers
- returning JSON, views, or component-backed HTML

If you are coming from NestJS, the mental model is similar: modules group controllers, controller attributes define prefixes, method attributes define handlers, and parameter attributes bind request data.

## The happy-path controller shape

Generate a resource:

```bash
assegai g r posts
```

You get a controller like this:

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

That is the core Assegai rhythm:

- the controller owns a route prefix
- each public handler declares an HTTP verb
- request data is bound through attributes
- business logic moves quickly into a provider

## Route prefixes live on the controller

The `#[Controller(...)]` attribute defines the local prefix for every handler on the class.

```php
#[Controller('posts')]
class PostsController
{
  #[Get]
  public function findAll(): array
  {
    return ['posts' => []];
  }
}
```

That maps to:

- `GET /posts`

An empty controller path means “root of the current module branch”:

```php
#[Controller('/')]
class HomeController
{
  #[Get]
  public function index(): string
  {
    return 'home';
  }
}
```

## Handler attributes define the HTTP method and local path

The current router recognizes these handler attributes:

- `#[Get]`
- `#[Post]`
- `#[Put]`
- `#[Patch]`
- `#[Delete]`
- `#[Head]`
- `#[Options]`
- `#[Sse]`

Examples:

```php
#[Get]
public function findAll(): array
{
  return [];
}

#[Post]
public function create(#[Body] object $body): array
{
  return ['ok' => true];
}

#[Patch(':id')]
public function update(#[Param('id')] int $id): array
{
  return ['id' => $id];
}
```

### Default response codes

The method attributes also set default status codes when the route is selected:

- `#[Get]` defaults to `200`
- `#[Post]` defaults to `201`
- the other HTTP method attributes leave the status at the normal default unless you override it
- `#[Sse]` also sets the `text/event-stream` headers

## How route paths are composed

The final route is:

```text
module branch prefix + controller prefix + handler path
```

For example:

```php
#[Controller('posts')]
class PostsController
{
  #[Get(':id')]
  public function findOne(#[Param('id')] int $id): array
  {
    return ['id' => $id];
  }
}
```

becomes:

- `GET /posts/:id`

If the controller lives inside an imported `ApiModule` branch with `#[Controller('api')]`, the same handler becomes:

- `GET /api/posts/:id`

## Route path patterns

Assegai supports several useful path shapes.

### Static routes

```php
#[Get('me')]
public function me(): array
{
  return ['id' => 'me'];
}
```

### Dynamic route params

```php
#[Get(':id')]
public function findOne(#[Param('id')] int $id): array
{
  return ['id' => $id];
}
```

### Constrained route params

Constrained params are one of the strongest parts of the current router.

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

The built-in constraints currently verified by the unit suite are:

- `int`
- `slug`
- `uuid`
- `alpha`
- `alnum`
- `hex`
- `ulid`

Use constraints when:

- a route param has an obvious shape
- static and dynamic routes would otherwise be ambiguous
- you want the route itself to document the contract

### Wildcard routes

The router also supports `*` wildcards in controller prefixes and handler paths.

```php
#[Controller('files')]
class FilesController
{
  #[Get('*')]
  public function catchAll(): string
  {
    return 'wildcard';
  }
}
```

Current matching behavior favors exact routes over wildcard routes when both could match the same request. That means you can keep a catch-all without breaking a more specific route at the branch root.

## Parameter binding

The handler signature is where Assegai starts to feel productive. You can bind request data directly into typed parameters.

The most useful binding attributes are:

- `#[Param('id')]`
- `#[Query('search')]`
- `#[Body]`
- `#[Req]`
- `#[Res]`
- `#[UploadedFile]`
- `#[HostParam('account')]`

Additional request-context attributes also exist:

- `#[Session]`
- `#[Ip]`

`#[Ip]` is deprecated in the source and should not be the default choice for new code.

### Route params with `#[Param]`

```php
#[Get(':id<int>')]
public function findOne(#[Param('id')] int $id): array
{
  return ['id' => $id];
}
```

If you omit the key, Assegai binds the whole route-param collection:

```php
#[Get(':id')]
public function debug(#[Param] object $params): object
{
  return $params;
}
```

### Automatic scalar param fallback

The router can also fall back to plain scalar arguments when the parameter name matches a captured route param:

```php
#[Get(':id')]
public function findOne(int $id): string
{
  return "post-$id";
}
```

This is handy for small handlers, but `#[Param('id')]` is still the clearest choice when you want the binding to be explicit.

### Query strings with `#[Query]`

Bind the whole query object:

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

Or bind one key:

```php
#[Get]
public function index(#[Query('search')] ?string $search = null): array
{
  return ['search' => $search];
}
```

### Request bodies with `#[Body]`

```php
#[Post]
public function create(#[Body] CreatePostDTO $dto): string
{
  return $this->postsService->create($dto);
}
```

This works well with generated DTOs because they are already shaped for Assegai's DI and validation flow.

Bind a single body field when you need a smaller shape:

```php
#[Post]
public function rename(#[Body('name')] string $name): array
{
  return ['name' => $name];
}
```

### Form posts work too

`Request` now handles:

- `application/json`
- `application/x-www-form-urlencoded`
- `multipart/form-data`

That means a controller can accept form submissions through the same `#[Body]` flow:

```php
#[Post]
public function submit(#[Body] object $body): object
{
  return $body;
}
```

or pair body data with uploaded files.

### Uploaded files with `#[UploadedFile]`

```php
<?php

use Assegai\Core\Attributes\UploadedFile;

#[Post('avatar')]
public function upload(#[UploadedFile] object $file): array
{
  return ['name' => $file->avatar['name'] ?? null];
}
```

Under the hood this is driven by `Request::getFile()`, which is populated during multipart form handling.

### Access to the raw request and response

You do not always need the lower-level objects, but they are available:

```php
<?php

use Assegai\Core\Attributes\Req;
use Assegai\Core\Attributes\Res;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;

#[Get('meta')]
public function meta(#[Req] Request $request, #[Res] Response $response): array
{
  $response->setStatus(202);

  return [
    'method' => $request->getMethod()->value,
    'path' => $request->getPath(),
    'host' => $request->getHostName(),
  ];
}
```

Reach for `#[Res]` when you need to manipulate the response object directly. For most handlers, returning a value is still the cleanest approach.

## Host and subdomain routing

Assegai controllers now support host-based routing in the `#[Controller]` attribute.

### Exact host match

```php
#[Controller(path: 'dashboard', host: 'admin.example.com')]
class AdminDashboardController
{
  #[Get]
  public function index(): string
  {
    return 'admin-dashboard';
  }
}
```

This handler only activates for requests to:

- `admin.example.com/dashboard`

### Multiple hosts

```php
#[Controller(path: 'reports', host: ['ops.example.com', 'support.example.com'])]
class ReportsController
{
  #[Get]
  public function index(): string
  {
    return 'reports';
  }
}
```

### Dynamic subdomains with `#[HostParam]`

```php
<?php

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\HostParam;
use Assegai\Core\Attributes\Http\Get;

#[Controller(path: 'dashboard', host: ':account.example.com')]
class TenantDashboardController
{
  #[Get]
  public function index(#[HostParam('account')] string $account): string
  {
    return "tenant-$account";
  }
}
```

For a request to `acme.example.com/dashboard`, the handler receives:

- `$account === 'acme'`

### Matching behavior

When multiple controllers share the same path, the router now prefers:

1. the most specific path
2. the most specific host match
3. the longest route when specificity ties

In practice that means:

- an exact host like `admin.example.com` beats `:account.example.com`
- a host-constrained controller beats a host-agnostic controller when both match the path
- a generic controller still serves as a fallback when no host pattern matches

### Proxy-aware host resolution

Request host matching is normalized from the incoming request metadata. The current `Request` implementation prefers:

1. `X-Forwarded-Host`
2. `Host`
3. `SERVER_NAME`
4. `REMOTE_HOST`

Ports are stripped and hostnames are normalized to lowercase before matching.

## Shaping the response

Controllers can return several kinds of results.

### Arrays and objects become JSON

```php
#[Get]
public function index(): array
{
  return ['ok' => true];
}
```

This is the default API-style path.

### Return a `Response` when you want manual control

```php
<?php

use Assegai\Core\Attributes\Res;
use Assegai\Core\Http\Responses\Response;

#[Get]
public function ping(#[Res] Response $response): Response
{
  return $response->plainText('pong');
}
```

The `Response` object gives you helpers like:

- `json(...)`
- `html(...)`
- `plainText(...)`
- `setStatus(...)`

### Return a classic `View` for server-rendered templates

```php
<?php

use Assegai\Core\Rendering\View;

#[Get]
public function home(): View
{
  return view('index', ['title' => 'Hello']);
}
```

### Return a component for component-backed HTML

```php
<?php

use Assegai\Core\Components\Interfaces\ComponentInterface;

#[Get]
public function about(): ComponentInterface
{
  return render(AboutComponent::class);
}
```

For a fuller walkthrough of server-rendered UI, HTMX, and Web Components, see [Pages and Components](./pages-and-components.md).

## Status-code and header overrides

### Override the response code

Use `#[HttpCode(...)]` or `#[ResponseStatus(...)]` when the method default is not what you want.

- `GET`, `PUT`, `PATCH`, `DELETE`, `HEAD`, and `OPTIONS` default to `200`
- `POST` defaults to `201`
- explicit status attributes override those defaults regardless of attribute order
- manual changes made through `#[Res] Response $response` still win at runtime

```php
<?php

use Assegai\Core\Attributes\Http\HttpCode;
use Assegai\Core\Attributes\ResponseStatus;

#[Post]
#[HttpCode(202)]
public function queueJob(): array
{
  return ['queued' => true];
}

#[Get('health')]
#[ResponseStatus(204)]
public function health(): array
{
  return [];
}
```

### Set response headers

```php
<?php

use Assegai\Core\Attributes\Http\Header;

#[Get('download')]
#[Header('X-Export-Version', '1')]
public function download(): array
{
  return ['ok' => true];
}
```

`#[Header]` is method-level and is applied only when the route is selected. You can repeat it to queue multiple headers.

### Redirect from a handler

Use `#[Redirect(...)]` when the handler should resolve as an HTTP redirect.

```php
<?php

use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Redirect;

#[Get('login')]
#[Redirect('/sign-in', 302)]
public function login(): string
{
  return 'Redirecting...';
}
```

If you need to decide dynamically, inject the response and redirect manually:

```php
<?php

use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Res;
use Assegai\Core\Http\Responses\Response;

#[Get('logout')]
public function logout(#[Res] Response $response): Response
{
  return $response->redirect('/goodbye', 303);
}
```

Route-level redirects and headers are applied before the handler runs, which means handler code can still override them when needed.

## Route trees are shaped by modules

Assegai routing is not defined in a second, separate router file. The route tree follows module composition.

If your root module imports `UsersModule`, `PostsModule`, and `AboutModule`, each feature brings its own controllers and prefixes with it:

- `UsersController` with `#[Controller('users')]` lives at `/users`
- `PostsController` with `#[Controller('posts')]` lives at `/posts`
- `AboutController` with `#[Controller('about')]` lives at `/about`

That keeps route structure aligned with code structure.

## The CLI can build nested controller branches

For example:

```bash
assegai g r api
assegai g r api/posts
```

This gives you nested modules and controllers, and the CLI updates the module graph for you:

- `AppModule` imports `ApiModule`
- `ApiModule` imports `PostsModule`

With controller prefixes like `api` and `posts`, the resulting branch looks like:

- `GET /api`
- `GET /api/:id`
- `GET /api/posts`
- `GET /api/posts/:id`

That pattern scales well when you want a dedicated API area without abandoning the feature-module structure.

## Guards and interceptors still sit naturally on controllers

Controllers can also participate in the cross-cutting pipeline.

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

For the deeper pipeline story, see [Guards, Interceptors, Pipes, and Middleware](./guards-interceptors-pipes-and-middleware.md).

## Practical advice

A good default controller style in Assegai looks like this:

- use one controller prefix per feature
- keep handlers thin and push real work into providers
- prefer constrained route params over manual parsing
- use explicit binding attributes when the signature would otherwise be ambiguous
- return plain arrays, objects, views, or components and let responders do their job
- use host-based routing when subdomains express real product boundaries, not just because they can

## Notes on current behavior

This guide prefers verified behavior over wishful API descriptions.

A few accuracy notes are worth keeping in mind:

- the current router recognizes the method decorators listed earlier, especially `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`, `Options`, and `Sse`
- constrained route params, wildcard precedence, nested module routing, and host-based controller matching are all covered by the current unit suite
- form submissions now flow through the same request-binding story as JSON bodies

If a controller pattern is important to you and not described here, it is usually a good sign to check the unit tests or add one before relying on it in production.
