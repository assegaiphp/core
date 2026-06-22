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
use Assegai\Core\Http\Responses\Responders\Responder;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\ModuleManager;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Core\Routing\Router;
use Assegai\Core\Session;
use Mocks\AttributeResolvedService;
use Mocks\ChildUsesParentExportedService;
use Mocks\ChildUsesParentPrivateService;
use Mocks\ExplicitRequestScopedService;
use Mocks\ExportVisibilityAppModule;
use Mocks\FrameworkAwareAppModule;
use Mocks\FrameworkAwareContractsService;
use Mocks\FrameworkAwareService;
use Mocks\LibraryCatalogModule;
use Mocks\LibraryFeatureAppModule;
use Mocks\LibraryFeatureModule;
use Mocks\LibraryReaderModule;
use Mocks\LibraryReaderService;
use Mocks\ParentExportVisibilityAppModule;
use Mocks\ParentPrivateModule;
use Mocks\ParentPrivateVisibilityAppModule;
use Mocks\ProviderOwnershipOrderingAppModule;
use Mocks\RequestCapturingService;
use Mocks\ResolverAwareAppModule;
use Mocks\ResolverOnlyAwareAppModule;
use Mocks\ResolverResolvedService;
use Mocks\RootPrivateConsumerService;
use Mocks\RootPublicConsumerService;
use Mocks\SharedChildMixedParentsAppModule;
use Mocks\SharedChildMixedParentsReversedAppModule;
use ReflectionException;
use ReflectionProperty;
use Tests\Support\UnitTester;

class InjectorCest
{
  private string $previousWorkingDirectory = '';
  private string $composerConfigFilename = '';
  private string $sourceDirectory = '';
  private string $logsDirectory = '';
  private string $configDirectory = '';
  private string $appConfigFilename = '';
  private string $projectConfigFilename = '';
  private bool $createdComposerConfig = false;
  private bool $createdSourceDirectory = false;
  private bool $createdConfigDirectory = false;
  private bool $createdAppConfig = false;
  private bool $createdProjectConfig = false;
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
    $workspace = dirname(__DIR__) . '/Unit/src_test';
    if (!is_dir($workspace)) {
      mkdir($workspace, 0777, true);
    }

    chdir($workspace);
    $this->composerConfigFilename = getcwd() . '/composer.json';
    $this->sourceDirectory = getcwd() . '/src';
    $this->logsDirectory = getcwd() . '/logs';
    $this->configDirectory = getcwd() . '/config';
    $this->appConfigFilename = $this->configDirectory . '/default.php';
    $this->projectConfigFilename = getcwd() . '/assegai.json';
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

    if (!is_dir($this->configDirectory)) {
      mkdir($this->configDirectory, 0777, true);
      $this->createdConfigDirectory = true;
    }

    file_put_contents($this->appConfigFilename, "<?php\n\nreturn [];\n");
    $this->createdAppConfig = true;

    file_put_contents($this->projectConfigFilename, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->createdProjectConfig = true;

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
    $this->resetSingleton(Responder::class);
    RuntimeContext::flush();

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
    $this->resetSingleton(Responder::class);
    RuntimeContext::flush();

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

    if ($this->createdAppConfig && is_file($this->appConfigFilename)) {
      unlink($this->appConfigFilename);
    }

    if ($this->createdProjectConfig && is_file($this->projectConfigFilename)) {
      unlink($this->projectConfigFilename);
    }

    if ($this->createdSourceDirectory && is_dir($this->sourceDirectory)) {
      rmdir($this->sourceDirectory);
    }

    if ($this->createdConfigDirectory && is_dir($this->configDirectory)) {
      rmdir($this->configDirectory);
    }

    chdir($this->previousWorkingDirectory);
  }

  public function testTheInjectorRecursivelyResolvesFrameworkServices(UnitTester $I): void
  {
    $service = Injector::getInstance()->resolve(FrameworkAwareService::class);

    $I->assertInstanceOf(FrameworkAwareService::class, $service);
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
    $I->assertSame(Request::current(), RuntimeContext::get(Request::class));
    $I->assertSame(Response::current(), RuntimeContext::get(Response::class));
    $I->assertSame(Request::current(), RuntimeContext::get(RequestInterface::class));
    $I->assertSame(Response::current(), RuntimeContext::get(ResponseInterface::class));
    $I->assertSame(Responder::current(), RuntimeContext::get(Responder::class));
    $I->assertSame(Responder::current(), RuntimeContext::get(ResponderInterface::class));
    $I->assertInstanceOf(ProjectConfig::class, $injector->get(ProjectConfig::class));
    $I->assertSame(Session::getInstance(), $injector->get(Session::class));
  }

  public function testModulesCanInjectProjectConfigWithoutListingItAsAProvider(UnitTester $I): void
  {
    $moduleManager = ModuleManager::getInstance();
    $moduleManager->setRootModuleClass(FrameworkAwareAppModule::class);
    $moduleManager->buildModuleTokensList(FrameworkAwareAppModule::class);
    $moduleManager->buildProviderTokensList();

    $service = Injector::getInstance()->resolve(FrameworkAwareService::class);
    $providerTokens = $moduleManager->getProviderTokens();

    $I->assertArrayNotHasKey(ProjectConfig::class, $providerTokens);
    $I->assertInstanceOf(FrameworkAwareService::class, $service);
    $I->assertSame(Request::current(), $service->request);
    $I->assertSame(Response::current(), $service->response);
    $I->assertInstanceOf(ProjectConfig::class, $service->projectConfig);
    $I->assertSame(Session::getInstance(), $service->session);
  }

  public function testParameterAttributesCanResolveDependenciesWithoutInjectorHardcoding(UnitTester $I): void
  {
    $service = Injector::getInstance()->resolve(AttributeResolvedService::class);

    $I->assertInstanceOf(AttributeResolvedService::class, $service);
    $I->assertSame('attribute-seam', $service->value->value);
  }

  public function testImportedModulesCanRegisterPackageParameterResolversBeforeProviderResolution(UnitTester $I): void
  {
    $app = AssegaiFactory::create(ResolverAwareAppModule::class);
    $app->boot();
    $service = Injector::getInstance()->resolve(ResolverResolvedService::class);

    $I->assertInstanceOf(App::class, $app);
    $I->assertInstanceOf(ResolverResolvedService::class, $service);
    $I->assertSame('resolved-by-registry', $service->value->value);
    $I->assertCount(1, Injector::getInstance()->getParameterResolvers());
  }

  public function testInjectorExtensionsCanBeConfiguredByImportedClassesWithoutTheModuleInterface(UnitTester $I): void
  {
    $app = AssegaiFactory::create(ResolverOnlyAwareAppModule::class);
    $app->boot();
    $service = Injector::getInstance()->resolve(ResolverResolvedService::class);

    $I->assertInstanceOf(App::class, $app);
    $I->assertInstanceOf(ResolverResolvedService::class, $service);
    $I->assertSame('resolved-by-registry', $service->value->value);
    $I->assertCount(1, Injector::getInstance()->getParameterResolvers());
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
    $currentRequest = Request::current();
    $currentResponse = Response::current();

    $I->assertNotSame($firstService, $secondService);
    $I->assertNotSame($firstService->request, $secondService->request);
    $I->assertSame($currentRequest, RuntimeContext::get(Request::class));
    $I->assertSame($currentResponse, RuntimeContext::get(Response::class));
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
    $I->assertSame($service->request, RuntimeContext::get(RequestInterface::class));
    $I->assertSame($service->response, RuntimeContext::get(ResponseInterface::class));
    $I->assertInstanceOf(ResponseEmitterInterface::class, $service->emitter);
    $I->assertInstanceOf(ResponderInterface::class, $service->responder);
  }

  public function testRequestCapturingServicesResolveAsRequestScoped(UnitTester $I): void
  {
    $app = AssegaiFactory::create(FrameworkAwareAppModule::class);
    $injector = Injector::getInstance();

    $firstService = $injector->resolve(RequestCapturingService::class);
    $firstExplicitScoped = $injector->resolve(ExplicitRequestScopedService::class);

    $_SERVER['REQUEST_URI'] = '/second';
    $_GET['path'] = '/second';

    $refreshRequestScope = new \ReflectionMethod($app, 'refreshRequestScope');
    $refreshRequestScope->invoke($app);

    $secondService = $injector->resolve(RequestCapturingService::class);
    $secondExplicitScoped = $injector->resolve(ExplicitRequestScopedService::class);

    $I->assertNotSame($firstService, $secondService);
    $I->assertNotSame($firstExplicitScoped, $secondExplicitScoped);
    $I->assertSame('second', trim($secondService->request->getPath(), '/'));
    $I->assertSame(Request::current(), $secondExplicitScoped->request);
    $I->assertNull($injector->get(RequestCapturingService::class));
    $I->assertNull($injector->get(ExplicitRequestScopedService::class));
  }

  public function testChildModulesCanInjectParentModuleExports(UnitTester $I): void
  {
    $app = AssegaiFactory::create(ParentExportVisibilityAppModule::class);
    $app->boot();

    $service = Injector::getInstance()->resolve(ChildUsesParentExportedService::class);

    $I->assertInstanceOf(ChildUsesParentExportedService::class, $service);
    $I->assertSame('parent-export', $service->parentService->value);
  }

  public function testChildModulesCannotInjectParentPrivateProviders(UnitTester $I): void
  {
    $app = AssegaiFactory::create(ParentPrivateVisibilityAppModule::class);
    $app->boot();

    try {
      Injector::getInstance()->resolve(ChildUsesParentPrivateService::class);
      $I->fail('Expected private parent provider resolution to fail.');
    } catch (\Assegai\Core\Exceptions\Container\ResolveException $exception) {
      $I->assertStringContainsString(ParentPrivateModule::class, $exception->getMessage());
      $I->assertStringContainsString('declares', $exception->getMessage());
      $I->assertStringContainsString('does not export', $exception->getMessage());
    }
  }

  public function testExportedSiblingProvidersReportMissingParentReExport(UnitTester $I): void
  {
    $app = AssegaiFactory::create(LibraryFeatureAppModule::class);
    $app->boot();

    try {
      Injector::getInstance()->resolve(LibraryReaderService::class);
      $I->fail('Expected sibling provider resolution without a parent re-export to fail.');
    } catch (\Assegai\Core\Exceptions\Container\ResolveException $exception) {
      $I->assertStringContainsString(LibraryCatalogModule::class, $exception->getMessage());
      $I->assertStringContainsString(LibraryReaderModule::class, $exception->getMessage());
      $I->assertStringContainsString(LibraryFeatureModule::class, $exception->getMessage());
      $I->assertStringContainsString('exports', $exception->getMessage());
      $I->assertStringContainsString('not visible', $exception->getMessage());
      $I->assertStringContainsString('Re-export', $exception->getMessage());
      $I->assertStringNotContainsString('does not export it', $exception->getMessage());
    }
  }

  public function testSharedChildModulesCannotInjectParentExportsFromOnlyOneBranch(UnitTester $I): void
  {
    $I->expectThrowable(
      \Assegai\Core\Exceptions\Container\ResolveException::class,
      fn() => AssegaiFactory::create(SharedChildMixedParentsAppModule::class)->boot(),
    );
  }

  public function testSharedChildParentExportChecksAreImportOrderIndependent(UnitTester $I): void
  {
    $I->expectThrowable(
      \Assegai\Core\Exceptions\Container\ResolveException::class,
      fn() => AssegaiFactory::create(SharedChildMixedParentsReversedAppModule::class)->boot(),
    );
  }

  public function testImportedModulesOnlyExposeExportedProviders(UnitTester $I): void
  {
    $app = AssegaiFactory::create(ExportVisibilityAppModule::class);
    $app->boot();
    $injector = Injector::getInstance();

    $publicConsumer = $injector->resolve(RootPublicConsumerService::class);

    $I->assertInstanceOf(RootPublicConsumerService::class, $publicConsumer);
    $I->assertSame('private', $publicConsumer->publicService->privateService->value);
    $I->expectThrowable(\Assegai\Core\Exceptions\Container\ResolveException::class, fn() => $injector->resolve(RootPrivateConsumerService::class));
  }

  public function testProviderOwnershipMapIsPopulatedBeforeResolvingDefaultProviders(UnitTester $I): void
  {
    $app = AssegaiFactory::create(ProviderOwnershipOrderingAppModule::class);

    $I->expectThrowable(
      \Assegai\Core\Exceptions\Container\ResolveException::class,
      fn() => $app->boot(),
    );
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
