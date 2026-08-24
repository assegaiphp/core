# Session Authentication End to End

This guide implements a complete browser login flow with:

- application-owned user lookup
- `SessionAuthStrategy` for credential verification and session state
- a guard for protected controllers
- `UnauthorizedException` as the guard failure signal
- the configurable `LoginRedirectFilter` for browser redirects
- restoration of the originally requested URL after login

No interceptor is required. Guards run before interceptors, while exception filters handle the exception raised when a guard denies access.

## Install the auth strategy package

```bash
composer require assegaiphp/auth
```

## Configure the framework-owned session

An Assegai application starts the session before controllers and guards run. Configure that session in `config/default.php`:

```php
<?php

return [
  'session' => [
    'name' => 'backoffice_session',
    'cookieLifetime' => 3600,
    'cookiePath' => '/',
    'cookieDomain' => '',
    'cookieSecure' => null, // Automatically true for HTTPS requests.
    'cookieHttpOnly' => true,
    'cookieSameSite' => 'Lax',
  ],
];
```

When `SessionAuthStrategy` runs inside Assegai, it uses this already-active session. Its standalone `session_name` and `session_lifetime` options are therefore unnecessary in framework application code.

## Add an authentication service

The auth package does not decide how users are stored. The application repository remains responsible for loading the user and its password hash.

```php
<?php

namespace App\Auth;

use App\Users\UsersRepository;
use Assegai\Auth\Strategies\SessionAuthStrategy;
use Assegai\Core\Attributes\Injectable;

#[Injectable]
final class AuthService
{
  public function __construct(private UsersRepository $users)
  {
  }

  public function login(string $email, string $password): ?object
  {
    $user = $this->users->findByEmail($email);

    if (!$user) {
      return null;
    }

    $strategy = new SessionAuthStrategy([
      'user' => $user,
      'username_field' => 'email',
      'password_field' => 'password',
    ]);

    if (!$strategy->authenticate([
      'email' => $email,
      'password' => $password,
    ])) {
      return null;
    }

    return $strategy->getUser();
  }

  public function isAuthenticated(): bool
  {
    return (new SessionAuthStrategy())->isAuthenticated();
  }

  public function currentUser(): ?object
  {
    return (new SessionAuthStrategy())->getUser();
  }

  public function logout(): void
  {
    (new SessionAuthStrategy())->logout();
  }
}
```

The strategy rotates the session identifier on successful login and stores a clone of the user with the password field removed.

## Add the session guard

The guard only decides whether execution may continue:

```php
<?php

namespace App\Auth;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Interfaces\ICanActivate;
use Assegai\Core\Interfaces\IExecutionContext;

#[Injectable]
final readonly class SessionAuthGuard implements ICanActivate
{
  public function __construct(private AuthService $auth)
  {
  }

  public function canActivate(IExecutionContext $context): bool
  {
    return $this->auth->isAuthenticated();
  }
}
```

The protected controller configures the exception raised by the guard and the browser response policy separately:

```php
<?php

namespace App\Dashboard;

use App\Auth\AuthService;
use App\Auth\SessionAuthGuard;
use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\UseFilters;
use Assegai\Core\Attributes\UseGuards;
use Assegai\Core\Exceptions\Filters\LoginRedirectFilter;
use Assegai\Core\Exceptions\Filters\LoginRedirectFilterOptions;
use Assegai\Core\Exceptions\Http\UnauthorizedException;

#[Controller('dashboard')]
#[UseGuards(SessionAuthGuard::class, UnauthorizedException::class)]
#[UseFilters(new LoginRedirectFilter(new LoginRedirectFilterOptions(
  loginUrl: '/auth/login',
  statusCode: 302,
  preserveTarget: true,
  targetSessionKey: 'auth.intended_url',
)))]
final readonly class DashboardController
{
  public function __construct(private AuthService $auth)
  {
  }

  #[Get]
  public function index(): array
  {
    return [
      'page' => 'dashboard',
      'user' => $this->auth->currentUser(),
    ];
  }
}
```

`loginUrl` is required; the filter does not assume an application route. The configured login path is automatically excluded from redirects so a mistakenly protected login route returns `401` instead of looping.

Only safe `GET` and `HEAD` targets are retained. Cross-origin, scheme-relative, and malformed targets are rejected.

The complete option surface is:

- `loginUrl`: required relative or HTTP(S) login URL
- `statusCode`: `301`, `302`, `303`, `307`, or `308`; defaults to `302`
- `preserveTarget`: whether to remember safe requested URLs; defaults to `true`
- `targetSessionKey`: dot-delimited session key used for that URL
- `excludedPaths`: additional exact paths that must remain `401` responses

## Add login and logout endpoints

```php
<?php

namespace App\Auth;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Res;
use Assegai\Core\Exceptions\Http\BadRequestException;
use Assegai\Core\Exceptions\Http\UnauthorizedException;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Session;
use stdClass;

#[Controller('auth')]
final readonly class AuthController
{
  public function __construct(
    private AuthService $auth,
    private Session $session,
  )
  {
  }

  #[Post('login')]
  public function login(#[Body] stdClass $body, #[Res] Response $response): Response
  {
    $email = $body->email ?? null;
    $password = $body->password ?? null;

    if (!is_string($email) || !is_string($password)) {
      throw new BadRequestException('Email and password are required.');
    }

    if (!$this->auth->login($email, $password)) {
      throw new UnauthorizedException('The supplied credentials are invalid.');
    }

    $target = $this->safeLocalTarget(
      $this->session->pull('auth.intended_url', '/dashboard'),
    );

    // 303 converts the successful form POST into a safe GET request.
    return $response->redirect($target, 303);
  }

  #[Post('logout')]
  public function logout(#[Res] Response $response): Response
  {
    $this->auth->logout();
    return $response->redirect('/auth/login', 303);
  }

  private function safeLocalTarget(mixed $target): string
  {
    if (
      !is_string($target) ||
      !str_starts_with($target, '/') ||
      str_starts_with($target, '//') ||
      preg_match('/[\r\n]/', $target)
    ) {
      return '/dashboard';
    }

    return $target;
  }
}
```

The login page itself can be served by a separate `GET /auth/login` handler. It must remain public.

## Register providers

```php
<?php

namespace App;

use App\Auth\AuthController;
use App\Auth\AuthService;
use App\Auth\SessionAuthGuard;
use App\Dashboard\DashboardController;
use App\Users\UsersRepository;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Interfaces\AssegaiModuleInterface;

#[Module(
  controllers: [AuthController::class, DashboardController::class],
  providers: [UsersRepository::class, AuthService::class, SessionAuthGuard::class],
)]
final class AppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}
```

## Browser-only global registration

For an application where every protected route is a browser page, register one configured filter during bootstrap:

```php
$app->useGlobalFilters(
  new LoginRedirectFilter(new LoginRedirectFilterOptions(
    loginUrl: '/auth/login',
    targetSessionKey: 'auth.intended_url',
  )),
  UnauthorizedException::class,
);
```

For mixed browser and API applications, keep the redirect filter scoped to browser controllers. API controllers can use the same guard and `UnauthorizedException` without `LoginRedirectFilter`, preserving their normal `401` response.

## Replace the default filter

The built-in options cover the common redirect flow. An application can replace it with any exception filter for tenant routing, content negotiation, custom challenge headers, auditing, or another policy:

```php
#[Injectable]
#[OnException(UnauthorizedException::class)]
final class ApplicationAuthenticationFilter implements ExceptionFilterInterface
{
  public function catch(Throwable $throwable, ArgumentsHost $host): void
  {
    $request = $host->switchToHttp()->getRequest();
    $response = $host->switchToHttp()->getResponse();

    if (str_starts_with($request->getPath(), '/api/')) {
      $response->reset();
      $response->setStatus(401);
      $response->jsonRaw(['error' => 'authentication_required']);
      return;
    }

    $response->reset();
    $response->redirect('/tenant/login', 302);
  }
}
```

Pass the class to `UseFilters` or `useGlobalFilters`. Assegai resolves class-string filters through dependency injection; configured instances are also supported.

## Production checklist

- Use `password_hash()` when storing passwords and let `SessionAuthStrategy` verify the resulting hash.
- Add CSRF protection to login, logout, and all state-changing session-authenticated forms.
- Rate-limit failed login attempts.
- Serve authentication routes over HTTPS and retain `HttpOnly` cookies.
- Use `SameSite=None` only with `Secure=true` when a cross-site flow actually requires it.
- Never redirect to an unvalidated user-supplied return URL.
