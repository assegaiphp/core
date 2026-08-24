<?php

namespace Tests\ExceptionFilters;

use Assegai\Core\ArgumentsHost;
use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Attributes\OnException;
use Assegai\Core\Attributes\UseFilters;
use Assegai\Core\Attributes\UseGuards;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Exceptions\Filters\LoginRedirectFilter;
use Assegai\Core\Exceptions\Filters\LoginRedirectFilterOptions;
use Assegai\Core\Exceptions\Http\UnauthorizedException;
use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Core\Interfaces\ICanActivate;
use Assegai\Core\Interfaces\IExecutionContext;
use RuntimeException;
use Throwable;

#[Injectable]
final class DenyGuard implements ICanActivate
{
  public function canActivate(IExecutionContext $context): bool
  {
    return false;
  }
}

#[Controller('members')]
#[UseGuards(DenyGuard::class, UnauthorizedException::class)]
#[UseFilters(new LoginRedirectFilter(new LoginRedirectFilterOptions(
  loginUrl: '/sign-in',
  statusCode: 303,
  targetSessionKey: 'security.intended_url',
)))]
final class ProtectedMembersController
{
  public static int $handlerCalls = 0;

  #[Get('profile')]
  public function profile(): array
  {
    self::$handlerCalls++;
    return ['name' => 'Ada'];
  }

  #[Get('handler')]
  #[UseFilters(HandlerUnauthorizedFilter::class)]
  public function handler(): array
  {
    self::$handlerCalls++;
    return ['name' => 'Grace'];
  }
}

#[Injectable]
#[OnException(UnauthorizedException::class)]
final class HandlerUnauthorizedFilter implements ExceptionFilterInterface
{
  public function catch(Throwable $throwable, ArgumentsHost $host): void
  {
    $response = $host->switchToHttp()->getResponse();
    $response->reset();
    $response->setStatus(409);
    $response->jsonRaw(['handled' => 'handler']);
  }
}

#[Controller('api/private')]
#[UseGuards(DenyGuard::class, UnauthorizedException::class)]
final class ApiPrivateController
{
  #[Get]
  public function index(): array
  {
    return ['private' => true];
  }
}

#[Injectable]
final class FilterAudit
{
  public static int $calls = 0;
}

#[Injectable]
#[OnException(RuntimeException::class)]
final readonly class TeapotExceptionFilter implements ExceptionFilterInterface
{
  public function __construct(private FilterAudit $audit)
  {
  }

  public function catch(Throwable $throwable, ArgumentsHost $host): void
  {
    FilterAudit::$calls++;
    $response = $host->switchToHttp()->getResponse();
    $response->reset();
    $response->setStatus(418);
    $response->jsonRaw(['handled' => $throwable->getMessage()]);
  }
}

#[Controller('failure')]
final class FailureController
{
  #[Get]
  public function index(): never
  {
    throw new RuntimeException('filtered failure');
  }
}

#[Module(
  controllers: [ProtectedMembersController::class, ApiPrivateController::class, FailureController::class],
  providers: [DenyGuard::class, HandlerUnauthorizedFilter::class, FilterAudit::class, TeapotExceptionFilter::class],
)]
final class ExceptionFilterAppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}

namespace Tests\Unit;

use Assegai\Core\ArgumentsHost;
use Assegai\Core\AssegaiFactory;
use Assegai\Core\Exceptions\Filters\LoginRedirectFilter;
use Assegai\Core\Exceptions\Filters\LoginRedirectFilterOptions;
use Assegai\Core\Exceptions\Http\UnauthorizedException;
use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Core\Session;
use InvalidArgumentException;
use RuntimeException;
use Tests\ExceptionFilters\ExceptionFilterAppModule;
use Tests\ExceptionFilters\FilterAudit;
use Tests\ExceptionFilters\ProtectedMembersController;
use Tests\ExceptionFilters\TeapotExceptionFilter;
use Tests\Support\UnitTester;
use Throwable;

final class ExceptionFiltersCest
{
  private string $originalWorkingDirectory = '';
  private string $originalSessionName = '';
  private string $workingDirectory = '';

  public function _before(): void
  {
    $this->originalWorkingDirectory = getcwd() ?: '.';
    $this->originalSessionName = session_name();
    $this->workingDirectory = sys_get_temp_dir() . '/assegai-core-exception-filter-tests';

    foreach (['config', 'src'] as $directory) {
      if (!is_dir($this->workingDirectory . '/' . $directory)) {
        mkdir($this->workingDirectory . '/' . $directory, 0777, true);
      }
    }

    file_put_contents($this->workingDirectory . '/.env', '');
    file_put_contents($this->workingDirectory . '/composer.json', json_encode([
      'name' => 'tests/exception-filter-app',
      'version' => '0.1.0',
    ], JSON_PRETTY_PRINT));
    file_put_contents($this->workingDirectory . '/assegai.json', '{}');
    file_put_contents(
      $this->workingDirectory . '/config/default.php',
      "<?php\n\nreturn ['session' => ['name' => 'assegai_filter_test']];\n",
    );

    chdir($this->workingDirectory);
    putenv('ASSEGAI_WORKING_DIR=' . $this->workingDirectory);
    $_ENV['ENV'] = 'PROD';
    ProtectedMembersController::$handlerCalls = 0;
    FilterAudit::$calls = 0;
    RuntimeContext::flush();
    $this->resetSession();
  }

  public function _after(): void
  {
    restore_error_handler();
    restore_exception_handler();
    RuntimeContext::flush();
    Request::setInstance(null);
    Response::setInstance(null);
    $this->resetSession();
    session_name($this->originalSessionName);
    putenv('ASSEGAI_WORKING_DIR');
    $_ENV['ENV'] = 'DEV';
    chdir($this->originalWorkingDirectory);

    foreach (['config/default.php', 'composer.json', 'assegai.json', '.env'] as $filename) {
      $path = $this->workingDirectory . '/' . $filename;

      if (is_file($path)) {
        unlink($path);
      }
    }

    if (is_dir($this->workingDirectory . '/config')) {
      rmdir($this->workingDirectory . '/config');
    }

    if (is_dir($this->workingDirectory . '/src')) {
      rmdir($this->workingDirectory . '/src');
    }

    $logFile = $this->workingDirectory . '/logs/assegai.log';

    if (is_file($logFile)) {
      unlink($logFile);
    }

    if (is_dir($this->workingDirectory . '/logs')) {
      rmdir($this->workingDirectory . '/logs');
    }

    if (is_dir($this->workingDirectory)) {
      rmdir($this->workingDirectory);
    }
  }

  public function testScopedLoginRedirectFilterIsTerminalAndPreservesTheTarget(UnitTester $I): void
  {
    $emitter = $this->createCapturingEmitter();
    $runtime = $this->createExecutingRuntime();
    $globalFilter = new class implements ExceptionFilterInterface {
      public int $calls = 0;

      public function catch(Throwable $throwable, ArgumentsHost $host): void
      {
        $this->calls++;
        $host->switchToHttp()->getResponse()->setStatus(499);
      }
    };
    $app = AssegaiFactory::create(ExceptionFilterAppModule::class, $runtime);
    $app->useGlobalFilters($globalFilter, UnauthorizedException::class);
    $app->setRuntimeRequestContext($this->requestContext('/members/profile?tab=security'));
    $app->setRuntimeResponseEmitter($emitter);

    $app->run();

    $I->assertCount(1, $emitter->emissions);
    $I->assertSame(303, $emitter->emissions[0]['response']?->getStatusCode());
    $I->assertSame('/sign-in', $emitter->emissions[0]['response']?->getHeader('Location'));
    $I->assertSame('no-store', $emitter->emissions[0]['response']?->getHeader('Cache-Control'));
    $I->assertSame('/members/profile?tab=security', $_SESSION['security']['intended_url'] ?? null);
    $I->assertSame('assegai_filter_test', session_name());
    $I->assertSame(0, ProtectedMembersController::$handlerCalls);
    $I->assertSame(0, $globalFilter->calls);
  }

  public function testMultipleGlobalFiltersAndDependencyInjectionAreSupported(UnitTester $I): void
  {
    $emitter = $this->createCapturingEmitter();
    $app = AssegaiFactory::create(ExceptionFilterAppModule::class, $this->createExecutingRuntime());
    $nonMatchingFilter = new class implements ExceptionFilterInterface {
      public int $calls = 0;

      public function catch(Throwable $throwable, ArgumentsHost $host): void
      {
        $this->calls++;
      }
    };
    $app->useGlobalFilters($nonMatchingFilter, UnauthorizedException::class);
    $app->useGlobalFilters(TeapotExceptionFilter::class, RuntimeException::class);
    $app->setRuntimeRequestContext($this->requestContext('/failure'));
    $app->setRuntimeResponseEmitter($emitter);

    $app->run();

    $I->assertCount(1, $emitter->emissions);
    $I->assertSame(418, $emitter->emissions[0]['response']?->getStatusCode());
    $I->assertSame(['handled' => 'filtered failure'], json_decode($emitter->emissions[0]['body'], true));
    $I->assertSame(1, FilterAudit::$calls);
    $I->assertSame(0, $nonMatchingFilter->calls);
  }

  public function testHandlerFilterTakesPrecedenceOverControllerAndGlobalFilters(UnitTester $I): void
  {
    $emitter = $this->createCapturingEmitter();
    $globalFilter = new class implements ExceptionFilterInterface {
      public int $calls = 0;

      public function catch(Throwable $throwable, ArgumentsHost $host): void
      {
        $this->calls++;
      }
    };
    $app = AssegaiFactory::create(ExceptionFilterAppModule::class, $this->createExecutingRuntime());
    $app->useGlobalFilters($globalFilter, UnauthorizedException::class);
    $app->setRuntimeRequestContext($this->requestContext('/members/handler'));
    $app->setRuntimeResponseEmitter($emitter);

    $app->run();

    $I->assertCount(1, $emitter->emissions);
    $I->assertSame(409, $emitter->emissions[0]['response']?->getStatusCode());
    $I->assertSame(['handled' => 'handler'], json_decode($emitter->emissions[0]['body'], true));
    $I->assertNull($emitter->emissions[0]['response']?->getHeader('Location'));
    $I->assertSame(0, ProtectedMembersController::$handlerCalls);
    $I->assertSame(0, $globalFilter->calls);
  }

  public function testGuardFailureWithoutRedirectFilterKeepsNormalApi401Response(UnitTester $I): void
  {
    $emitter = $this->createCapturingEmitter();
    $app = AssegaiFactory::create(ExceptionFilterAppModule::class, $this->createExecutingRuntime());
    $app->setRuntimeRequestContext($this->requestContext('/api/private'));
    $app->setRuntimeResponseEmitter($emitter);

    $app->run();

    $I->assertCount(1, $emitter->emissions);
    $I->assertSame(401, $emitter->emissions[0]['response']?->getStatusCode());
    $I->assertNull($emitter->emissions[0]['response']?->getHeader('Location'));
  }

  public function testLoginPathIsExcludedFromRedirectLoops(UnitTester $I): void
  {
    $request = Request::createFromRuntimeContext($this->requestContext('/sign-in'));
    $response = Response::create();
    RuntimeContext::set(Request::class, $request);
    RuntimeContext::set(Response::class, $response);
    Request::setInstance($request);
    Response::setInstance($response);
    $filter = new LoginRedirectFilter(new LoginRedirectFilterOptions('/sign-in'));

    $filter->catch(new UnauthorizedException(), new ArgumentsHost());

    $I->assertSame(401, $response->getStatusCode());
    $I->assertFalse($response->isRedirect());
    $I->assertNull($response->getHeader('Location'));
  }

  public function testCrossOriginRequestUriIsNotStoredAsAnIntendedTarget(UnitTester $I): void
  {
    $request = Request::createFromRuntimeContext($this->requestContext('https://attacker.example/private?source=login'));
    $response = Response::create();
    RuntimeContext::set(Request::class, $request);
    RuntimeContext::set(Response::class, $response);
    Request::setInstance($request);
    Response::setInstance($response);
    $filter = new LoginRedirectFilter(new LoginRedirectFilterOptions(
      loginUrl: '/sign-in',
      targetSessionKey: 'security.intended_url',
    ));

    $filter->catch(new UnauthorizedException(), new ArgumentsHost());

    $I->assertSame(302, $response->getStatusCode());
    $I->assertSame('/sign-in', $response->getHeader('Location'));
    $I->assertFalse(Session::getInstance()->has('security.intended_url'));
  }

  public function testSessionPullReturnsAndRemovesNestedValues(UnitTester $I): void
  {
    $_SESSION = ['auth' => ['intended_url' => '/dashboard']];
    $session = Session::getInstance();

    $I->assertSame('/dashboard', $session->pull('auth.intended_url', '/'));
    $I->assertFalse($session->has('auth.intended_url'));
    $I->assertSame('/', $session->get('auth.missing', '/'));
  }

  public function testRedirectOptionsRejectUnsafeLoginUrls(UnitTester $I): void
  {
    $I->expectThrowable(
      InvalidArgumentException::class,
      static fn() => new LoginRedirectFilterOptions("/login\r\nX-Test: injected"),
    );
    $I->expectThrowable(
      InvalidArgumentException::class,
      static fn() => new LoginRedirectFilterOptions('//attacker.example/login'),
    );
    $I->expectThrowable(
      InvalidArgumentException::class,
      static fn() => new LoginRedirectFilterOptions('javascript:alert(1)'),
    );
    $I->expectThrowable(
      InvalidArgumentException::class,
      static fn() => new LoginRedirectFilterOptions('/login', statusCode: 304),
    );
  }

  private function requestContext(string $uri): RuntimeRequestContext
  {
    $queryString = parse_url($uri, PHP_URL_QUERY);

    return new RuntimeRequestContext(
      server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => $uri,
        'QUERY_STRING' => is_string($queryString) ? $queryString : '',
        'HTTP_HOST' => 'example.test',
        'REQUEST_SCHEME' => 'https',
        'HTTP_ACCEPT' => 'text/html',
      ],
      query: ['path' => parse_url($uri, PHP_URL_PATH) ?: '/'],
    );
  }

  private function createExecutingRuntime(): HttpRuntimeInterface
  {
    return new class implements HttpRuntimeInterface {
      public function getName(): string
      {
        return 'php';
      }

      public function run(AppInterface $app, callable $handler): void
      {
        $handler();
      }
    };
  }

  private function createCapturingEmitter(): object
  {
    return new class implements ResponseEmitterInterface {
      /** @var array<int, array{body: string, response: ResponseInterface|null}> */
      public array $emissions = [];

      public function emit(string $body, ?ResponseInterface $response = null): void
      {
        $this->emissions[] = compact('body', 'response');
      }
    };
  }

  private function resetSession(): void
  {
    if (session_status() === PHP_SESSION_ACTIVE) {
      $_SESSION = [];
      session_destroy();
    }

    $_SESSION = [];

    if (session_status() === PHP_SESSION_NONE && session_id() !== '') {
      session_id('');
    }
  }
}
