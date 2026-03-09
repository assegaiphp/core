<?php

namespace Tests\Unit;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\ControllerManager;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\ModuleManager;
use Assegai\Core\Routing\Router;
use Codeception\Attribute\Incomplete;
use Codeception\Attribute\Skip;
use Mocks\MockController;
use Mocks\NestedApiController;
use Mocks\NestedAppModule;
use Mocks\NestedFeaturesController;
use Mocks\NestedRootController;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use stdClass;
use Tests\Support\UnitTester;

class RouterCest
{
  const string VALID_TEST_URI = '/test';
  const string INVALID_TEST_URI = '/invalid';
  private ?Router $router = null;
  private ?MockController $controller = null;
  public function _before(): void
  {
    include dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (! in_array(MockController::class, get_declared_classes()) )
    {
      include dirname(__DIR__, 2) . '/tests/Mocks/MockController.php';
    }

    $_SERVER['REQUEST_URI'] = self::VALID_TEST_URI;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_SCHEME'] = 'http';
    $_SERVER['REMOTE_HOST'] = 'localhost';
    $_SERVER['QUERY_STRING'] = '';
    $_GET['path'] = self::VALID_TEST_URI;

    $this->resetRequestSingleton();
    $this->router = Router::getInstance();
    $this->controller = new MockController();
  }

  public function _after(): void
  {
    $this->resetRequestSingleton();
    $this->router = null;
  }

  public function testTheGetInstanceMethod(UnitTester $I): void
  {
    $instance = Router::getInstance();
    $I->assertInstanceOf(Router::class, $instance);
  }

  public function testTheGetRequestMethod(UnitTester $I): void
  {
    $request = $this->router->getRequest();
    $I->assertInstanceOf(Request::class, $request);
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   */
  #[Skip("TODO: Fix the test")]
  public function testTheGetActivatedControllerMethod(UnitTester $I): void
  {
    $_GET['path'] = self::VALID_TEST_URI;
    $request = $this->router->getRequest();
    $controllerTokensList = [
      MockController::class => new ReflectionClass(MockController::class)
    ];
    $this->controller = $this->router->getActivatedController($request, $controllerTokensList);
    $I->assertInstanceOf(MockController::class, $this->controller);
  }

  #[Skip]
  public function testTheHandleRequestMethod(UnitTester $I): void
  {
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testNestedModulesResolveToTheClosestMatchingController(UnitTester $I): void
  {
    $controllerTokensList = $this->buildNestedControllerTokens();
    $request = $this->makeRequest('/api/features/1');
    $controller = $this->router->getActivatedController($request, $controllerTokensList);
    $handler = $this->router->getActivatedHandler(
      $this->router->getControllerHandlers($controller),
      $controller,
      $request,
    );

    $I->assertInstanceOf(NestedFeaturesController::class, $controller);
    $I->assertSame('findOne', $handler?->getName());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testNestedHandlersBindRouteParamsWithoutExplicitParamAttributes(UnitTester $I): void
  {
    $controllerTokensList = $this->buildNestedControllerTokens();
    $request = $this->makeRequest('/api/features/1');
    $controller = $this->router->getActivatedController($request, $controllerTokensList);
    $response = $this->router->handleRequest($request, $controller);

    $I->assertInstanceOf(NestedFeaturesController::class, $controller);
    $I->assertSame('feature-1', $response->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testNestedModulesFallBackToTheLastMatchedAncestorController(UnitTester $I): void
  {
    $controllerTokensList = $this->buildNestedControllerTokens();
    $request = $this->makeRequest('/api/unknown');
    $controller = $this->router->getActivatedController($request, $controllerTokensList);

    $I->assertInstanceOf(NestedApiController::class, $controller);
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testUnknownRootPathsFallBackToTheRootController(UnitTester $I): void
  {
    $controllerTokensList = $this->buildNestedControllerTokens();
    $request = $this->makeRequest('/unknown');
    $controller = $this->router->getActivatedController($request, $controllerTokensList);

    $I->assertInstanceOf(NestedRootController::class, $controller);
  }

  /**
   * @return array<string, ReflectionClass>
   * @throws EntryNotFoundException
   * @throws HttpException
   */
  private function buildNestedControllerTokens(): array
  {
    $moduleManager = ModuleManager::getInstance();
    $controllerManager = ControllerManager::getInstance();

    $moduleManager->setRootModuleClass(NestedAppModule::class);
    $moduleManager->buildModuleTokensList(NestedAppModule::class);

    return $controllerManager->buildControllerTokensList($moduleManager->getModuleTokens());
  }

  /**
   * @throws ReflectionException
   */
  private function makeRequest(string $path): Request
  {
    $_GET['path'] = $path;
    $this->resetRequestSingleton();

    return $this->router->getRequest();
  }

  /**
   * @throws ReflectionException
   */
  private function resetRequestSingleton(): void
  {
    $requestInstanceProperty = new ReflectionProperty(Request::class, 'instance');
    $requestInstanceProperty->setValue(null, null);
  }
}
