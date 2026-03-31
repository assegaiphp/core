<?php

namespace Tests\Runtime;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Enumerations\Scope;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Core\Interfaces\OnApplicationBootstrapInterface;
use Assegai\Core\Interfaces\OnApplicationShutdownInterface;
use Assegai\Core\Interfaces\OnModuleInitInterface;

#[Module]
class DummyAppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}

#[Controller('runtime-probe')]
class RuntimeProbeController
{
  #[Get]
  public function index(RequestInterface $request): Response
  {
    return Response::current()->jsonRaw([
      'path' => $request->getPath(),
      'limit' => $request->getLimit(),
      'lang' => $request->getLang(),
      'cookie' => $request->getCookies('mode'),
      'remote_ip' => $request->getRemoteIp(),
    ]);
  }
}

#[Module(controllers: [RuntimeProbeController::class])]
class RuntimeAwareAppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}

#[Controller('lifecycle-probe')]
final class LifecycleProbeController
{
  #[Get]
  public function index(): Response
  {
    return Response::current()->jsonRaw(['ok' => true]);
  }
}

final class LifecycleCounters
{
  public static int $moduleInitCalls = 0;
  public static int $applicationBootstrapCalls = 0;
  public static int $applicationShutdownCalls = 0;
  public static int $requestScopedBootstrapCalls = 0;

  public static function reset(): void
  {
    self::$moduleInitCalls = 0;
    self::$applicationBootstrapCalls = 0;
    self::$applicationShutdownCalls = 0;
    self::$requestScopedBootstrapCalls = 0;
  }
}

#[Injectable]
final class LifecycleAwareProvider implements OnModuleInitInterface, OnApplicationBootstrapInterface, OnApplicationShutdownInterface
{
  public function onModuleInit(): void
  {
    LifecycleCounters::$moduleInitCalls++;
  }

  public function onApplicationBootstrap(): void
  {
    LifecycleCounters::$applicationBootstrapCalls++;
  }

  public function onApplicationShutdown(): void
  {
    LifecycleCounters::$applicationShutdownCalls++;
  }
}

#[Injectable(options: ['scope' => Scope::REQUEST, 'durable' => false])]
final class RequestScopedLifecycleProvider implements OnApplicationBootstrapInterface
{
  public function onApplicationBootstrap(): void
  {
    LifecycleCounters::$requestScopedBootstrapCalls++;
  }
}

#[Module(
  controllers: [LifecycleProbeController::class],
  providers: [
    LifecycleAwareProvider::class,
    RequestScopedLifecycleProvider::class,
  ],
)]
final class LifecycleAwareAppModule implements AssegaiModuleInterface
{
  public function configure(MiddlewareConsumer $consumer): void
  {
  }
}

namespace Unit;

use Assegai\Core\App;
use Assegai\Core\AssegaiFactory;
use Assegai\Core\ControllerManager;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Http\Responses\Emitters\OpenSwooleResponseEmitter;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Responders\Responder;
use Assegai\Core\Http\Responses\Response as HttpResponse;
use Assegai\Core\Injector;
use Assegai\Core\ModuleManager;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Core\Runtimes\OpenSwoole\Interfaces\OpenSwooleHttpServerInterface;
use Assegai\Core\Runtimes\OpenSwoole\Interfaces\OpenSwooleServerFactoryInterface;
use Assegai\Core\Runtimes\OpenSwooleHttpRuntime;
use Assegai\Core\Runtimes\PhpHttpRuntime;
use Assegai\Core\Routing\Router;
use InvalidArgumentException;
use ReflectionProperty;
use Tests\Runtime\RuntimeAwareAppModule;
use Tests\Support\UnitTester;

class RuntimeCest
{
  private string $workingDirectory = '';
  private string $originalWorkingDirectory = '';
  private string $composerFile = '';
  private string $sourceDirectory = '';
  private string $viewsDirectory = '';
  private string $configDirectory = '';
  private string $appConfigFile = '';
  private string $projectConfigFile = '';
  private bool $createdSourceDirectory = false;
  private bool $createdViewsDirectory = false;
  private bool $createdConfigDirectory = false;

  public function _before(UnitTester $I): void
  {
    $this->originalWorkingDirectory = getcwd() ?: '.';
    $this->workingDirectory = dirname(__DIR__, 2) . '/tests/Unit/src_test';
    $this->composerFile = $this->workingDirectory . '/composer.json';
    $this->sourceDirectory = $this->workingDirectory . '/src';
    $this->viewsDirectory = $this->sourceDirectory . '/Views';
    $this->configDirectory = $this->workingDirectory . '/config';
    $this->appConfigFile = $this->configDirectory . '/default.php';
    $this->projectConfigFile = $this->workingDirectory . '/assegai.json';

    if (!is_dir($this->workingDirectory)) {
      @mkdir($this->workingDirectory, 0777, true);
    }

    chdir($this->workingDirectory);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_SCHEME'] = 'http';
    $_SERVER['HTTP_HOST'] = 'localhost:5050';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['CONTENT_TYPE'] = '';
    $_GET['path'] = '';

    if (!is_dir($this->sourceDirectory)) {
      @mkdir($this->sourceDirectory, 0777, true);
      $this->createdSourceDirectory = true;
    }

    if (!is_dir($this->viewsDirectory)) {
      @mkdir($this->viewsDirectory, 0777, true);
      $this->createdViewsDirectory = true;
    }

    if (!is_file($this->composerFile)) {
      file_put_contents($this->composerFile, json_encode([
        'name' => 'tests/runtime-app',
        'version' => '0.1.0',
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    if (!is_dir($this->configDirectory)) {
      @mkdir($this->configDirectory, 0777, true);
      $this->createdConfigDirectory = true;
    }

    file_put_contents($this->appConfigFile, "<?php\n\nreturn [];\n");
    file_put_contents($this->projectConfigFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->resetSingleton(Injector::class);
    $this->resetSingleton(ModuleManager::class);
    $this->resetSingleton(ControllerManager::class);
    $this->resetSingleton(Router::class);
    $this->resetSingleton(Request::class);
    $this->resetSingleton(HttpResponse::class);
    $this->resetSingleton(Responder::class);
    RuntimeContext::flush();
    \Tests\Runtime\LifecycleCounters::reset();
  }

  public function _after(UnitTester $I): void
  {
    if (is_file($this->composerFile)) {
      unlink($this->composerFile);
    }

    if (is_file($this->appConfigFile)) {
      unlink($this->appConfigFile);
    }

    if (is_file($this->projectConfigFile)) {
      unlink($this->projectConfigFile);
    }

    if ($this->createdViewsDirectory && is_dir($this->viewsDirectory)) {
      rmdir($this->viewsDirectory);
    }

    if ($this->createdSourceDirectory && is_dir($this->sourceDirectory)) {
      rmdir($this->sourceDirectory);
    }

    if ($this->createdConfigDirectory && is_dir($this->configDirectory)) {
      rmdir($this->configDirectory);
    }

    $this->resetSingleton(Injector::class);
    $this->resetSingleton(ModuleManager::class);
    $this->resetSingleton(ControllerManager::class);
    $this->resetSingleton(Router::class);
    $this->resetSingleton(Request::class);
    $this->resetSingleton(HttpResponse::class);
    $this->resetSingleton(Responder::class);
    RuntimeContext::flush();
    \Tests\Runtime\LifecycleCounters::reset();

    chdir($this->originalWorkingDirectory);
  }

  public function testAssegaiFactoryUsesTheDefaultPhpRuntime(UnitTester $I): void
  {
    $app = AssegaiFactory::create('Tests\\Runtime\\DummyAppModule');

    $I->assertInstanceOf(PhpHttpRuntime::class, $app->getRuntime());
    $I->assertSame('php', $app->getRuntime()->getName());
  }

  public function testFactoryCreatesFreshManagerGraphsPerApp(UnitTester $I): void
  {
    $firstApp = AssegaiFactory::create('Tests\\Runtime\\DummyAppModule');
    $secondApp = AssegaiFactory::create('Tests\\Runtime\\DummyAppModule');

    $I->assertNotSame($this->readProtectedProperty($firstApp, 'injector'), $this->readProtectedProperty($secondApp, 'injector'));
    $I->assertNotSame($this->readProtectedProperty($firstApp, 'moduleManager'), $this->readProtectedProperty($secondApp, 'moduleManager'));
    $I->assertNotSame($this->readProtectedProperty($firstApp, 'controllerManager'), $this->readProtectedProperty($secondApp, 'controllerManager'));
    $I->assertNotSame($this->readProtectedProperty($firstApp, 'router'), $this->readProtectedProperty($secondApp, 'router'));
  }

  public function testAppRunDelegatesToTheInjectedRuntime(UnitTester $I): void
  {
    $runtime = new class implements HttpRuntimeInterface {
      public int $calls = 0;
      public ?AppInterface $app = null;

      public function getName(): string
      {
        return 'fake';
      }

      public function run(AppInterface $app, callable $handler): void
      {
        $this->calls++;
        $this->app = $app;
      }
    };

    $app = AssegaiFactory::create('Tests\\Runtime\\DummyAppModule', $runtime);
    $app->run();

    $I->assertSame(1, $runtime->calls);
    $I->assertSame($app, $runtime->app);
    $I->assertSame($runtime, $app->getRuntime());
  }

  public function testFactoryCanResolveNamedRuntimes(UnitTester $I): void
  {
    $I->assertInstanceOf(PhpHttpRuntime::class, AssegaiFactory::resolveRuntime('php'));
    $I->assertInstanceOf(OpenSwooleHttpRuntime::class, AssegaiFactory::resolveRuntime('openswoole'));
    $I->assertInstanceOf(OpenSwooleHttpRuntime::class, AssegaiFactory::resolveRuntime('swoole'));
  }

  public function testFactoryCanCreateFromProjectRuntimeConfig(UnitTester $I): void
  {
    file_put_contents($this->projectConfigFile, json_encode([
      'development' => [
        'server' => [
          'runtime' => 'openswoole',
          'host' => '127.0.0.1',
          'port' => 9512,
          'openswoole' => [
            'workerNum' => 2,
            'taskWorkerNum' => 1,
            'maxRequest' => 250,
            'enableCoroutine' => true,
            'hookFlags' => 'all',
          ],
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $app = AssegaiFactory::createFromProject('Tests\\Runtime\\DummyAppModule', $this->workingDirectory);

    $I->assertInstanceOf(OpenSwooleHttpRuntime::class, $app->getRuntime());
    $I->assertSame('openswoole', $app->getRuntime()->getName());
    $I->assertSame('127.0.0.1', $app->getRuntime()->getHost());
    $I->assertSame(9512, $app->getRuntime()->getPort());
    $I->assertSame(2, $app->getRuntime()->getSettings()['workerNum'] ?? null);
    $I->assertSame(1, $app->getRuntime()->getSettings()['taskWorkerNum'] ?? null);
    $I->assertSame(250, $app->getRuntime()->getSettings()['maxRequest'] ?? null);
  }

  public function testEnvironmentRuntimeOverridesTheDefaultFactoryPath(UnitTester $I): void
  {
    putenv('ASSEGAI_RUNTIME=openswoole');
    putenv('ASSEGAI_HOST=127.0.0.1');
    putenv('ASSEGAI_PORT=9513');

    try {
      $app = AssegaiFactory::create('Tests\\Runtime\\DummyAppModule');

      $I->assertInstanceOf(OpenSwooleHttpRuntime::class, $app->getRuntime());
      $I->assertSame('openswoole', $app->getRuntime()->getName());
    } finally {
      putenv('ASSEGAI_RUNTIME');
      putenv('ASSEGAI_HOST');
      putenv('ASSEGAI_PORT');
    }
  }

  public function testFactoryRejectsUnknownRuntimeNames(UnitTester $I): void
  {
    $I->expectThrowable(InvalidArgumentException::class, static function (): void {
      AssegaiFactory::resolveRuntime('mystery');
    });
  }

  public function testOpenSwooleRuntimeRejectsUnsupportedSettings(UnitTester $I): void
  {
    $I->expectThrowable(InvalidArgumentException::class, static function (): void {
      new OpenSwooleHttpRuntime(settings: [
        'unsupportedThing' => true,
      ]);
    });
  }

  public function testOpenSwooleRuntimeRejectsInvalidBindingsAndNumericSettings(UnitTester $I): void
  {
    $I->expectThrowable(InvalidArgumentException::class, static function (): void {
      new OpenSwooleHttpRuntime(host: ' ', port: 9501);
    });

    $I->expectThrowable(InvalidArgumentException::class, static function (): void {
      new OpenSwooleHttpRuntime(host: '127.0.0.1', port: 0);
    });

    $I->expectThrowable(InvalidArgumentException::class, static function (): void {
      new OpenSwooleHttpRuntime(settings: [
        'workerNum' => 0,
      ]);
    });
  }

  public function testAppCanHydrateRequestScopeFromRuntimeContext(UnitTester $I): void
  {
    $_GET = ['path' => '/wrong-source', 'limit' => '999'];
    $_COOKIE = ['mode' => 'wrong'];
    $_SERVER['REMOTE_ADDR'] = '127.0.0.99';
    $capturingEmitter = new class implements ResponseEmitterInterface {
      public string $body = '';
      public ?ResponseInterface $response = null;

      public function emit(string $body, ?ResponseInterface $response = null): void
      {
        $this->body = $body;
        $this->response = $response;
      }
    };

    $app = AssegaiFactory::create(RuntimeAwareAppModule::class);
    $app->setRuntimeRequestContext(new RuntimeRequestContext(
      server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/runtime-probe',
        'QUERY_STRING' => 'limit=25&lang=ny',
        'HTTP_HOST' => 'runtime.local',
        'REQUEST_SCHEME' => 'https',
        'REMOTE_ADDR' => '10.0.0.8',
      ],
      query: [
        'path' => '/runtime-probe',
        'limit' => '25',
        'lang' => 'ny',
      ],
      cookies: [
        'mode' => 'runtime',
      ],
    ));
    $app->setRuntimeResponseEmitter($capturingEmitter);

    $refreshRequestScope = new \ReflectionMethod($app, 'refreshRequestScope');
    $refreshRequestScope->invoke($app);

    $request = Request::current();

    $I->assertSame('/runtime-probe', $request->getPath());
    $I->assertSame(25, $request->getLimit());
    $I->assertSame('ny', $request->getLang());
    $I->assertSame('runtime', $request->getCookies('mode'));
    $I->assertSame('10.0.0.8', $request->getRemoteIp());
    $I->assertSame($capturingEmitter, RuntimeContext::get(ResponseEmitterInterface::class));

    $app->clearRuntimeOverrides();
  }

  public function testOpenSwooleResponseEmitterBridgesResponseMetadata(UnitTester $I): void
  {
    $target = new class {
      public ?int $status = null;
      /** @var array<string, string> */
      public array $headers = [];
      public string $body = '';

      public function isWritable(): bool
      {
        return true;
      }

      public function status(int $status): void
      {
        $this->status = $status;
      }

      public function header(string $name, string $value): void
      {
        $this->headers[$name] = $value;
      }

      public function end(string $body): void
      {
        $this->body = $body;
      }
    };

    $emitter = new OpenSwooleResponseEmitter($target);
    $response = HttpResponse::create();
    $response->setStatus(201);
    $response->setHeader('X-Runtime', 'openswoole');
    $response->jsonRaw(['ok' => true]);

    $emitter->emit('{"ok":true}', $response);

    $I->assertSame(201, $target->status);
    $I->assertSame('openswoole', $target->headers['X-Runtime'] ?? null);
    $I->assertSame('application/json', $target->headers['Content-Type'] ?? null);
    $I->assertSame('{"ok":true}', $target->body);
  }

  public function testCurrentFrameworkObjectsPreferRuntimeContext(UnitTester $I): void
  {
    $request = Request::createFromRuntimeContext(new RuntimeRequestContext(
      server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/runtime-context',
        'QUERY_STRING' => '',
        'REQUEST_SCHEME' => 'http',
      ],
      query: [
        'path' => '/runtime-context',
      ],
    ));
    $response = HttpResponse::create();
    $responder = Responder::create();

    RuntimeContext::set(Request::class, $request);
    RuntimeContext::set(HttpResponse::class, $response);
    RuntimeContext::set(Responder::class, $responder);

    $I->assertSame($request, Request::current());
    $I->assertSame($response, HttpResponse::current());
    $I->assertSame($responder, Responder::current());
  }

  public function testLifecycleHooksRunOnceAndSkipRequestScopedProviders(UnitTester $I): void
  {
    $app = AssegaiFactory::create(\Tests\Runtime\LifecycleAwareAppModule::class);
    $prepareApplicationGraph = new \ReflectionMethod($app, 'prepareApplicationGraph');
    $prepareApplicationGraph->invoke($app);
    $prepareApplicationGraph->invoke($app);

    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$moduleInitCalls);
    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$applicationBootstrapCalls);
    $I->assertSame(0, \Tests\Runtime\LifecycleCounters::$requestScopedBootstrapCalls);
  }

  public function testShutdownHooksRunOnce(UnitTester $I): void
  {
    $app = AssegaiFactory::create(\Tests\Runtime\LifecycleAwareAppModule::class);

    $app->boot();
    $app->shutdown();
    $app->shutdown();

    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$applicationShutdownCalls);
  }

  public function testAppRunInvokesShutdownHooksForNonOpenSwooleRuntimes(UnitTester $I): void
  {
    $capturingEmitter = new class implements ResponseEmitterInterface {
      public string $body = '';
      public ?ResponseInterface $response = null;

      public function emit(string $body, ?ResponseInterface $response = null): void
      {
        $this->body = $body;
        $this->response = $response;
      }
    };

    $runtime = new class implements HttpRuntimeInterface {
      public function getName(): string
      {
        return 'test-runtime';
      }

      public function run(AppInterface $app, callable $handler): void
      {
        $handler();
      }
    };

    $app = AssegaiFactory::create(\Tests\Runtime\LifecycleAwareAppModule::class, $runtime);
    $app->setRuntimeRequestContext(new RuntimeRequestContext(
      server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/lifecycle-probe',
        'QUERY_STRING' => '',
        'HTTP_HOST' => 'runtime.local',
        'REQUEST_SCHEME' => 'http',
      ],
      query: [
        'path' => '/lifecycle-probe',
      ],
    ));
    $app->setRuntimeResponseEmitter($capturingEmitter);

    $app->run();

    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$moduleInitCalls);
    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$applicationBootstrapCalls);
    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$applicationShutdownCalls);
    $I->assertSame(200, $capturingEmitter->response?->getStatusCode());
    $I->assertSame(['ok' => true], json_decode($capturingEmitter->body, true));
  }

  public function testOpenSwooleRuntimeCanHandleAFullWorkerLifecycle(UnitTester $I): void
  {
    $request = new class {
      public array $server = [
        'request_method' => 'GET',
        'request_uri' => '/lifecycle-probe',
        'query_string' => '',
        'remote_addr' => '10.20.30.40',
        'server_protocol' => 'HTTP/1.1',
      ];
      public array $header = [
        'host' => 'runtime.local',
      ];
      public array $get = [
        'path' => '/lifecycle-probe',
      ];
      public array $post = [];
      public array $cookie = [];
      public array $files = [];

      public function rawContent(): string
      {
        return '';
      }
    };

    $response = new class {
      public ?int $status = null;
      /** @var array<string, string> */
      public array $headers = [];
      public string $body = '';
      public bool $writable = true;

      public function isWritable(): bool
      {
        return $this->writable;
      }

      public function status(int $status): void
      {
        $this->status = $status;
      }

      public function header(string $name, string $value): void
      {
        $this->headers[$name] = $value;
      }

      public function end(string $body): void
      {
        $this->body = $body;
        $this->writable = false;
      }
    };

    $server = new class($request, $response) implements OpenSwooleHttpServerInterface {
      /** @var array<string, mixed> */
      public array $settings = [];
      /** @var array<string, callable> */
      public array $handlers = [];

      public function __construct(
        private readonly object $request,
        private readonly object $response,
      )
      {
      }

      public function set(array $settings): void
      {
        $this->settings = $settings;
      }

      public function on(string $event, callable $handler): void
      {
        $this->handlers[$event] = $handler;
      }

      public function start(): void
      {
        ($this->handlers['workerStart'])();
        ($this->handlers['request'])($this->request, $this->response);
        ($this->handlers['workerExit'])();
      }
    };

    $factory = new class($server) implements OpenSwooleServerFactoryInterface {
      public function __construct(
        private readonly OpenSwooleHttpServerInterface $server,
      )
      {
      }

      public function create(string $host, int $port): OpenSwooleHttpServerInterface
      {
        return $this->server;
      }
    };

    $runtime = new OpenSwooleHttpRuntime(
      host: '127.0.0.1',
      port: 9514,
      settings: [
        'workerNum' => 2,
        'taskWorkerNum' => 1,
        'maxRequest' => 50,
        'enableCoroutine' => true,
        'hookFlags' => 'all',
      ],
      serverFactory: $factory,
    );

    $app = AssegaiFactory::create(\Tests\Runtime\LifecycleAwareAppModule::class, $runtime);
    $app->run();

    $I->assertSame(2, $server->settings['worker_num'] ?? null);
    $I->assertSame(1, $server->settings['task_worker_num'] ?? null);
    $I->assertSame(50, $server->settings['max_request'] ?? null);
    $I->assertTrue($server->settings['enable_coroutine'] ?? false);
    $I->assertArrayHasKey('hook_flags', $server->settings);
    $I->assertSame(200, $response->status);
    $I->assertSame('application/json', $response->headers['Content-Type'] ?? null);
    $I->assertSame(['ok' => true], json_decode($response->body, true));
    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$moduleInitCalls);
    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$applicationBootstrapCalls);
    $I->assertSame(1, \Tests\Runtime\LifecycleCounters::$applicationShutdownCalls);
    $I->assertSame(0, \Tests\Runtime\LifecycleCounters::$requestScopedBootstrapCalls);
    $I->assertSame(1, $this->readProtectedProperty($app, 'applicationGraphBuildCount'));
    $I->assertSame(1, $this->readProtectedProperty($app, 'middlewareBuildCount'));
    $I->assertNull(RuntimeContext::get(Request::class));
    $I->assertNull(RuntimeContext::get(HttpResponse::class));
    $I->assertNull(RuntimeContext::get(Responder::class));
  }

  public function testOpenSwooleRuntimeCanResolveHookFlagListsIntoBitmasks(UnitTester $I): void
  {
    if (!defined('SWOOLE_HOOK_FILE')) {
      define('SWOOLE_HOOK_FILE', 4);
    }

    if (!defined('SWOOLE_HOOK_SLEEP')) {
      define('SWOOLE_HOOK_SLEEP', 8);
    }

    $expectedHookFlags = constant('SWOOLE_HOOK_FILE') | constant('SWOOLE_HOOK_SLEEP');
    $request = new class {
      public array $server = [
        'request_method' => 'GET',
        'request_uri' => '/runtime-probe',
        'query_string' => '',
        'remote_addr' => '10.20.30.42',
        'server_protocol' => 'HTTP/1.1',
      ];
      public array $header = [
        'host' => 'runtime.local',
      ];
      public array $get = [
        'path' => '/runtime-probe',
      ];
      public array $post = [];
      public array $cookie = [];
      public array $files = [];

      public function rawContent(): string
      {
        return '';
      }
    };

    $response = new class {
      public ?int $status = null;
      /** @var array<string, string> */
      public array $headers = [];
      public string $body = '';
      public bool $writable = true;

      public function isWritable(): bool
      {
        return $this->writable;
      }

      public function status(int $status): void
      {
        $this->status = $status;
      }

      public function header(string $name, string $value): void
      {
        $this->headers[$name] = $value;
      }

      public function end(string $body): void
      {
        $this->body = $body;
        $this->writable = false;
      }
    };

    $server = new class($request, $response) implements OpenSwooleHttpServerInterface {
      /** @var array<string, mixed> */
      public array $settings = [];
      /** @var array<string, callable> */
      public array $handlers = [];

      public function __construct(
        private readonly object $request,
        private readonly object $response,
      )
      {
      }

      public function set(array $settings): void
      {
        $this->settings = $settings;
      }

      public function on(string $event, callable $handler): void
      {
        $this->handlers[$event] = $handler;
      }

      public function start(): void
      {
        ($this->handlers['request'])($this->request, $this->response);
      }
    };

    $factory = new class($server) implements OpenSwooleServerFactoryInterface {
      public function __construct(
        private readonly OpenSwooleHttpServerInterface $server,
      )
      {
      }

      public function create(string $host, int $port): OpenSwooleHttpServerInterface
      {
        return $this->server;
      }
    };

    $runtime = new OpenSwooleHttpRuntime(
      host: '127.0.0.1',
      port: 9516,
      settings: [
        'hookFlags' => ['file', 'sleep'],
      ],
      serverFactory: $factory,
    );

    $app = AssegaiFactory::create(RuntimeAwareAppModule::class, $runtime);
    $app->run();

    $I->assertSame($expectedHookFlags, $server->settings['hook_flags'] ?? null);
    $I->assertSame(200, $response->status);
  }

  public function testOpenSwooleRuntimeRoutesEscapedHandlerFailuresThroughFrameworkHandlers(UnitTester $I): void
  {
    $request = new class {
      public array $server = [
        'request_method' => 'GET',
        'request_uri' => '/runtime-failure',
        'query_string' => '',
        'remote_addr' => '10.20.30.41',
        'server_protocol' => 'HTTP/1.1',
      ];
      public array $header = [
        'host' => 'runtime.local',
      ];
      public array $get = [
        'path' => '/runtime-failure',
      ];
      public array $post = [];
      public array $cookie = [];
      public array $files = [];

      public function rawContent(): string
      {
        return '';
      }
    };

    $response = new class {
      public ?int $status = null;
      /** @var array<string, string> */
      public array $headers = [];
      public string $body = '';
      public bool $writable = true;

      public function isWritable(): bool
      {
        return $this->writable;
      }

      public function status(int $status): void
      {
        $this->status = $status;
      }

      public function header(string $name, string $value): void
      {
        $this->headers[$name] = $value;
      }

      public function end(string $body): void
      {
        $this->body = $body;
        $this->writable = false;
      }
    };

    $server = new class($request, $response) implements OpenSwooleHttpServerInterface {
      /** @var array<string, mixed> */
      public array $settings = [];
      /** @var array<string, callable> */
      public array $handlers = [];

      public function __construct(
        private readonly object $request,
        private readonly object $response,
      )
      {
      }

      public function set(array $settings): void
      {
        $this->settings = $settings;
      }

      public function on(string $event, callable $handler): void
      {
        $this->handlers[$event] = $handler;
      }

      public function start(): void
      {
        ($this->handlers['request'])($this->request, $this->response);
      }
    };

    $factory = new class($server) implements OpenSwooleServerFactoryInterface {
      public function __construct(
        private readonly OpenSwooleHttpServerInterface $server,
      )
      {
      }

      public function create(string $host, int $port): OpenSwooleHttpServerInterface
      {
        return $this->server;
      }
    };

    $runtime = new OpenSwooleHttpRuntime(
      host: '127.0.0.1',
      port: 9515,
      settings: [],
      serverFactory: $factory,
    );

    $app = AssegaiFactory::create(\Tests\Runtime\DummyAppModule::class, $runtime);

    $runtime->run($app, static function (): void {
      throw new \RuntimeException('OpenSwoole failure');
    });

    $I->assertSame(500, $response->status);
    $I->assertSame('text/html', $response->headers['Content-Type'] ?? null);
    $I->assertNotSame('OpenSwoole failure', trim($response->body));
    $I->assertTrue(
      str_contains(strtolower($response->body), '<html')
      || str_contains(strtolower($response->body), '<!doctype html')
    );
    $I->assertNull(RuntimeContext::get(Request::class));
    $I->assertNull(RuntimeContext::get(HttpResponse::class));
    $I->assertNull(RuntimeContext::get(Responder::class));
  }

  /**
   * @param class-string $className
   * @return void
   * @throws \ReflectionException
   */
  private function resetSingleton(string $className): void
  {
    $instanceProperty = new ReflectionProperty($className, 'instance');
    $instanceProperty->setValue(null, null);
  }

  /**
   * @param object $instance
   * @param string $propertyName
   * @return mixed
   * @throws \ReflectionException
   */
  private function readProtectedProperty(object $instance, string $propertyName): mixed
  {
    $property = new ReflectionProperty($instance, $propertyName);
    return $property->getValue($instance);
  }
}
