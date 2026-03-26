<?php

namespace Tests\Runtime;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Get;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Enumerations\Scope;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\AssegaiModuleInterface;
use Assegai\Core\Interfaces\OnApplicationBootstrapInterface;
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
  public static int $requestScopedBootstrapCalls = 0;

  public static function reset(): void
  {
    self::$moduleInitCalls = 0;
    self::$applicationBootstrapCalls = 0;
    self::$requestScopedBootstrapCalls = 0;
  }
}

#[Injectable]
final class LifecycleAwareProvider implements OnModuleInitInterface, OnApplicationBootstrapInterface
{
  public function onModuleInit(): void
  {
    LifecycleCounters::$moduleInitCalls++;
  }

  public function onApplicationBootstrap(): void
  {
    LifecycleCounters::$applicationBootstrapCalls++;
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

  public function testFactoryRejectsUnknownRuntimeNames(UnitTester $I): void
  {
    $I->expectThrowable(InvalidArgumentException::class, static function (): void {
      AssegaiFactory::resolveRuntime('mystery');
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
