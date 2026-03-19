<?php

namespace Tests\Unit;

use Assegai\Core\App;
use Assegai\Core\AssegaiFactory;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\ControllerManager;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\ModuleManager;
use Assegai\Core\Routing\Router;
use Assegai\Core\Session;
use Mocks\FrameworkAwareAppModule;
use Mocks\FrameworkAwareContractsService;
use Mocks\FrameworkAwareService;
use ReflectionException;
use ReflectionProperty;
use Tests\Support\UnitTester;

class InjectorCest
{
  private string $previousWorkingDirectory = '';
  private string $composerConfigFilename = '';
  private string $sourceDirectory = '';
  private string $logsDirectory = '';
  private bool $createdComposerConfig = false;
  private bool $createdSourceDirectory = false;
  private array $previousServer = [];
  private array $previousGet = [];
  private array $previousPost = [];
  private array $previousFiles = [];
  private string $previousSessionSavePath = '';

  public function _before(): void
  {
    include dirname(__DIR__, 2) . '/vendor/autoload.php';

    if (!in_array(FrameworkAwareService::class, get_declared_classes(), true)) {
      include dirname(__DIR__, 2) . '/tests/Mocks/MockInjector.php';
    }

    $this->previousWorkingDirectory = getcwd() ?: '.';
    $this->previousServer = $_SERVER;
    $this->previousGet = $_GET;
    $this->previousPost = $_POST;
    $this->previousFiles = $_FILES;
    $this->previousSessionSavePath = session_save_path();
    chdir(dirname(__DIR__) . '/Unit/src_test');
    $this->composerConfigFilename = getcwd() . '/composer.json';
    $this->sourceDirectory = getcwd() . '/src';
    $this->logsDirectory = getcwd() . '/logs';
    $this->createdComposerConfig = false;
    $this->createdSourceDirectory = false;

    if (!is_file($this->composerConfigFilename)) {
      file_put_contents($this->composerConfigFilename, json_encode([
        'name' => 'assegaiphp/core-test-app',
        'autoload' => [
          'psr-4' => [
            'Tests\\\\Fixtures\\\\' => 'src/',
          ],
        ],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      $this->createdComposerConfig = true;
    }

    if (!is_dir($this->sourceDirectory)) {
      mkdir($this->sourceDirectory, 0777, true);
      $this->createdSourceDirectory = true;
    }

    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_SCHEME'] = 'http';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REMOTE_HOST'] = 'localhost';
    $_SERVER['QUERY_STRING'] = '';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_GET['path'] = '/';
    $_POST = [];
    $_FILES = [];
    $_SESSION = [];
    session_save_path('/tmp/assegaiphp-core-session-tests');
    if (!is_dir(session_save_path())) {
      mkdir(session_save_path(), 0777, true);
    }

    $this->resetSingleton(Injector::class);
    $this->resetSingleton(ModuleManager::class);
    $this->resetSingleton(ControllerManager::class);
    $this->resetSingleton(Router::class);
    $this->resetSingleton(Request::class);
    $this->resetSingleton(Response::class);

    header_remove();
    http_response_code(200);
  }

  public function _after(): void
  {
    $this->resetSingleton(Injector::class);
    $this->resetSingleton(ModuleManager::class);
    $this->resetSingleton(ControllerManager::class);
    $this->resetSingleton(Router::class);
    $this->resetSingleton(Request::class);
    $this->resetSingleton(Response::class);

    header_remove();
    http_response_code(200);
    $_SERVER = $this->previousServer;
    $_GET = $this->previousGet;
    $_POST = $this->previousPost;
    $_FILES = $this->previousFiles;
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }
    session_save_path($this->previousSessionSavePath);

    $logFilename = $this->logsDirectory . '/assegai.log';
    if (is_file($logFilename)) {
      unlink($logFilename);
    }

    if (is_dir($this->logsDirectory)) {
      rmdir($this->logsDirectory);
    }

    if ($this->createdComposerConfig && is_file($this->composerConfigFilename)) {
      unlink($this->composerConfigFilename);
    }

    if ($this->createdSourceDirectory && is_dir($this->sourceDirectory)) {
      rmdir($this->sourceDirectory);
    }

    chdir($this->previousWorkingDirectory);
  }

  public function testTheInjectorRecursivelyResolvesFrameworkServices(UnitTester $I): void
  {
    $service = Injector::getInstance()->resolve(FrameworkAwareService::class);

    $I->assertInstanceOf(FrameworkAwareService::class, $service);
    $I->assertSame(Request::getInstance(), $service->request);
    $I->assertSame(Response::getInstance(), $service->response);
    $I->assertSame(Request::current(), $service->request);
    $I->assertSame(Response::current(), $service->response);
    $I->assertInstanceOf(ProjectConfig::class, $service->projectConfig);
    $I->assertInstanceOf(Session::class, $service->session);
    $I->assertSame(Session::getInstance(), $service->session);
    $I->assertSame($service->projectConfig, Injector::getInstance()->get(ProjectConfig::class));
  }

  public function testAppBootstrapRegistersFrameworkServicesByDefault(UnitTester $I): void
  {
    $app = AssegaiFactory::create(FrameworkAwareAppModule::class);
    $injector = Injector::getInstance();

    $I->assertInstanceOf(App::class, $app);
    $I->assertSame($app, $injector->get(App::class));
    $I->assertSame($app, $injector->get(AppInterface::class));
    $I->assertSame(Request::getInstance(), $injector->get(Request::class));
    $I->assertSame(Response::getInstance(), $injector->get(Response::class));
    $I->assertSame(Request::getInstance(), $injector->get(RequestInterface::class));
    $I->assertSame(Response::getInstance(), $injector->get(ResponseInterface::class));
    $I->assertSame(Request::current(), $injector->get(Request::class));
    $I->assertSame(Response::current(), $injector->get(Response::class));
    $I->assertInstanceOf(ProjectConfig::class, $injector->get(ProjectConfig::class));
    $I->assertSame(Session::getInstance(), $injector->get(Session::class));
  }

  public function testModulesCanInjectProjectConfigWithoutListingItAsAProvider(UnitTester $I): void
  {
    $moduleManager = ModuleManager::getInstance();
    $moduleManager->setRootModuleClass(FrameworkAwareAppModule::class);
    $moduleManager->buildModuleTokensList(FrameworkAwareAppModule::class);
    $moduleManager->buildProviderTokensList();

    $service = Injector::getInstance()->get(FrameworkAwareService::class);
    $providerTokens = $moduleManager->getProviderTokens();

    $I->assertArrayNotHasKey(ProjectConfig::class, $providerTokens);
    $I->assertInstanceOf(FrameworkAwareService::class, $service);
    $I->assertSame(Request::getInstance(), $service->request);
    $I->assertSame(Response::getInstance(), $service->response);
    $I->assertInstanceOf(ProjectConfig::class, $service->projectConfig);
    $I->assertSame(Session::getInstance(), $service->session);
  }

  public function testRefreshingRequestScopeRebindsRequestScopedServices(UnitTester $I): void
  {
    $_SERVER['REQUEST_URI'] = '/first';
    $_GET['path'] = '/first';

    $app = AssegaiFactory::create(FrameworkAwareAppModule::class);
    $injector = Injector::getInstance();
    $firstService = $injector->resolve(FrameworkAwareService::class);

    $_SERVER['REQUEST_URI'] = '/second';
    $_GET['path'] = '/second';

    $refreshRequestScope = new \ReflectionMethod($app, 'refreshRequestScope');
    $refreshRequestScope->invoke($app);

    $secondService = $injector->resolve(FrameworkAwareService::class);
    $currentRequest = Request::getInstance();
    $currentResponse = Response::getInstance();

    $I->assertNotSame($firstService, $secondService);
    $I->assertNotSame($firstService->request, $secondService->request);
    $I->assertSame($currentRequest, $injector->get(Request::class));
    $I->assertSame($currentResponse, $injector->get(Response::class));
    $I->assertSame(Request::current(), $currentRequest);
    $I->assertSame(Response::current(), $currentResponse);
    $I->assertSame($currentRequest, $secondService->request);
    $I->assertSame($currentResponse, $secondService->response);
    $I->assertSame('second', trim($currentRequest->getPath(), '/'));
  }

  public function testFrameworkContractsResolveToRequestScopedBindings(UnitTester $I): void
  {
    $app = AssegaiFactory::create(FrameworkAwareAppModule::class);
    $service = Injector::getInstance()->resolve(FrameworkAwareContractsService::class);

    $I->assertInstanceOf(App::class, $app);
    $I->assertInstanceOf(FrameworkAwareContractsService::class, $service);
    $I->assertSame(Request::current(), $service->request);
    $I->assertSame(Response::current(), $service->response);
    $I->assertSame($service->request, Injector::getInstance()->get(RequestInterface::class));
    $I->assertSame($service->response, Injector::getInstance()->get(ResponseInterface::class));
    $I->assertInstanceOf(ResponseEmitterInterface::class, $service->emitter);
    $I->assertInstanceOf(ResponderInterface::class, $service->responder);
  }

  public function testSessionLifecycleStartsAndClosesPerRequest(UnitTester $I): void
  {
    $app = AssegaiFactory::create(FrameworkAwareAppModule::class);

    $startSession = new \ReflectionMethod($app, 'startSessionForCurrentRequest');
    $closeSession = new \ReflectionMethod($app, 'closeSessionForCurrentRequest');

    $I->assertSame(PHP_SESSION_NONE, session_status());

    $startSession->invoke($app);
    $I->assertSame(PHP_SESSION_ACTIVE, session_status());

    $startSession->invoke($app);
    $I->assertSame(PHP_SESSION_ACTIVE, session_status());

    $closeSession->invoke($app);
    $I->assertSame(PHP_SESSION_NONE, session_status());
  }

  /**
   * @param class-string $className
   * @throws ReflectionException
   */
  private function resetSingleton(string $className): void
  {
    $instanceProperty = new ReflectionProperty($className, 'instance');
    $instanceProperty->setValue(null, null);
  }
}
