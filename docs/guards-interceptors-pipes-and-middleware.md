# Guards, Interceptors, Pipes, and Middleware

Assegai has several ways to apply cross-cutting behavior without pushing everything into controllers and services.

The main tools are:

- guards for access decisions
- interceptors for behavior around handler execution
- pipes for request transformation and validation
- middleware as a lower-level HTTP concept

The first three are clearly on the main request path in the current core runtime. Middleware has an API surface too, but it is a more cautious topic in the current codebase, so this guide treats it accordingly.

## Generate the building blocks

The CLI can scaffold three of these concerns directly:

```bash
assegai generate guard auth
assegai generate interceptor empty-result
assegai generate pipe trim-strings
```

That creates feature folders like:

```text
src/Auth/AuthGuard.php
src/EmptyResult/EmptyResultInterceptor.php
src/TrimStrings/TrimStringsPipe.php
```

These generators are useful because they keep the framework-facing interfaces in place from the start.

## Guards

Guards answer one question:

> Should this handler be allowed to run?

They implement `ICanActivate` and receive an execution context.

If you want reusable authentication helpers for those guards, `assegaiphp/auth` currently ships session and JWT strategies. It helps with credential verification and auth state, but your app still owns user lookup, login endpoints, and the guard logic that decides whether a request should proceed.

### A generated guard

```php
<?php

namespace Assegaiphp\BlogApi\Auth;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Interfaces\ICanActivate;
use Assegai\Core\Interfaces\IExecutionContext;

#[Injectable]
class AuthGuard implements ICanActivate
{
  public function canActivate(IExecutionContext $context): bool
  {
    return true;
  }
}
```

### Apply a guard to a controller

```php
<?php

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\UseGuards;
use Assegaiphp\BlogApi\Auth\AuthGuard;

#[UseGuards(AuthGuard::class)]
#[Controller('admin')]
class AdminController
{
  #[Get]
  public function index(): array
  {
    return ['area' => 'admin'];
  }
}
```

### Apply a guard to a single handler

```php
<?php

use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\UseGuards;
use Assegaiphp\BlogApi\Auth\AuthGuard;

#[Delete(':id<int>')]
#[UseGuards(AuthGuard::class)]
public function deleteById(#[Param('id')] int $id): array
{
  return ['deleted' => $id];
}
```

Guards are the right place for:

- authentication
- role checks
- tenant checks
- feature flags

The `UseGuards` attribute also accepts a custom exception class if you want a different failure response than the default forbidden path.

## Interceptors

Interceptors wrap handler execution. They are useful when the concern is not "who is allowed in?" but rather "what should happen around this call?"

That makes them a good fit for:

- transforming results
- normalizing empty responses
- timing or logging work
- response decoration

### A generated interceptor

```php
<?php

namespace Assegaiphp\BlogApi\EmptyResult;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Interfaces\IAssegaiInterceptor;

#[Injectable]
class EmptyResultInterceptor implements IAssegaiInterceptor
{
  public function intercept(ExecutionContext $context): ?callable
  {
    return function () use ($context) {
      return $context;
    };
  }
}
```

### Use a built-in interceptor

Core ships with `EmptyResultInterceptor`, which is useful when an empty result should become a `404`.

```php
<?php

use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\UseInterceptors;
use Assegai\Core\Interceptors\EmptyResultInterceptor;

#[Get(':id<int>')]
#[UseInterceptors(EmptyResultInterceptor::class)]
public function findById(#[Param('id')] int $id): array
{
  return [];
}
```

### Register a global interceptor

Interceptors can also be added at app level:

```php
<?php

use Assegai\Core\AssegaiFactory;
use Assegai\Core\Interceptors\EmptyResultInterceptor;
use Assegaiphp\BlogApi\AppModule;

require './vendor/autoload.php';

function bootstrap(): void
{
  $app = AssegaiFactory::create(AppModule::class);
  $app->useGlobalInterceptors(EmptyResultInterceptor::class);
  $app->run();
}

bootstrap();
```

That gives you a clean way to apply one behavior consistently across many handlers.

## Pipes

Pipes transform or validate request data before it reaches the service layer.

This is where Assegai starts to feel especially Nest-like:

- handlers stay small
- DTOs stay meaningful
- request cleanup happens at the edge

The built-in pipe surface includes:

- `ValidationPipe`
- `ParseIntPipe`
- `ParseBoolPipe`
- `ParseFloatPipe`
- `ParseArrayPipe`
- `ParseFilePipe`
- `MapProperties`

The most practical walkthrough is in [Request Data and Validation](./request-data-and-validation.md), but the short version is:

```php
<?php

use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Post;
use Assegaiphp\BlogApi\Posts\DTOs\CreatePostDTO;
use Assegaiphp\BlogApi\TrimStrings\TrimStringsPipe;

#[Post]
public function create(
  #[Body(pipes: TrimStringsPipe::class)] CreatePostDTO $dto,
): mixed {
  return $this->postsService->create($dto);
}
```

At app level there is also a `useGlobalPipes(...)` API on `App`, though the clearest verified request-time pipe path in the current core is still decorator-bound handling such as `#[Body(pipes: ...)]`.

## Middleware

Middleware exists in the current core surface through:

- `MiddlewareInterface`
- `MiddlewareConsumer`
- `Route`
- `AssegaiModuleInterface::configure(...)`

That tells us the intended model:

- a module can configure middleware
- middleware can be applied to routes
- middleware runs at the HTTP layer

At the same time, this part of the codebase looks less mature than guards, interceptors, and pipes. In this repo I did not verify a complete end-to-end middleware registration path with the same confidence as the other three tools.

So the practical guidance today is:

- reach for guards when the concern is authorization
- reach for interceptors when the concern wraps handler execution
- reach for pipes when the concern is request transformation or validation
- treat middleware as a lower-level, still-evolving surface unless your app version already depends on it

## Which tool to choose

Use a guard when:

- the handler should be blocked unless a condition is true

Use an interceptor when:

- the handler should still run, but the framework should do something around it

Use a pipe when:

- the request data should be validated or transformed before the handler uses it

Use middleware when:

- you truly need lower-level HTTP request/response behavior and you have verified the middleware path in your target app version
