<?php

namespace Tests\Unit;

use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\ControllerManager;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\ModuleManager;
use Assegai\Core\Routing\Router;
use Mocks\MiddlewareAppModule;
use Mocks\MiddlewareShortCircuitAppModule;
use Mocks\MiddlewareTrace;
use Mocks\MiddlewareTestController;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Tests\Support\UnitTester;

class MiddlewareCest
{
  private ?Router $router = null;

  public function _before(): void
  {
    include dirname(__DIR__, 2) . '/vendor/autoload.php';

    if (!in_array(\Mocks\NestedRootController::class, get_declared_classes(), true)) {
      include dirname(__DIR__, 2) . '/tests/Mocks/MockController.php';
    }

    if (!in_array(MiddlewareTestController::class, get_declared_classes(), true)) {
      include dirname(__DIR__, 2) . '/tests/Mocks/MockMiddleware.php';
    }

    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_SCHEME'] = 'http';
    $_SERVER['REMOTE_HOST'] = 'localhost';
    $_SERVER['QUERY_STRING'] = '';
    $_GET['path'] = '/';

    MiddlewareTrace::reset();
    $this->resetRequestSingleton();
    $this->resetResponseSingleton();
    $this->router = Router::getInstance();
    $this->router->setMiddlewareConsumer(null);
  }

  public function _after(): void
  {
    $this->router?->setMiddlewareConsumer(null);
    $this->resetRequestSingleton();
    $this->resetResponseSingleton();
    MiddlewareTrace::reset();
    $this->router = null;
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testControllerMiddlewareWrapsHandlersInRegistrationOrder(UnitTester $I): void
  {
    $result = $this->dispatch('/middleware', MiddlewareAppModule::class);

    $I->assertSame('index', $result['response']->getBody());
    $I->assertSame([
      'first:before',
      'second:before',
      'controller:index',
      'second:after',
      'first:after',
    ], MiddlewareTrace::$events);
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testExcludedRoutesSkipControllerBoundMiddleware(UnitTester $I): void
  {
    $result = $this->dispatch('/middleware/42', MiddlewareAppModule::class);

    $I->assertSame('show-42', $result['response']->getBody());
    $I->assertSame(['controller:show'], MiddlewareTrace::$events);
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testRouteTargetsCanRestrictMiddlewareByHttpMethod(UnitTester $I): void
  {
    $result = $this->dispatch('/middleware', MiddlewareAppModule::class, 'POST');

    $I->assertSame('create', $result['response']->getBody());
    $I->assertSame([
      'first:before',
      'second:before',
      'post:before',
      'controller:create',
      'post:after',
      'second:after',
      'first:after',
    ], MiddlewareTrace::$events);
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testMiddlewareCanShortCircuitWithoutInvokingTheHandler(UnitTester $I): void
  {
    $result = $this->dispatch('/middleware-short-circuit', MiddlewareShortCircuitAppModule::class);

    $I->assertSame('blocked', $result['response']->getBody());
    $I->assertSame(['stop'], MiddlewareTrace::$events);
  }

  /**
   * @param string $path
   * @param class-string $rootModuleClass
   * @param string $method
   * @return array{controller: object, request: Request, response: Response}
   * @throws ContainerException
   * @throws EntryNotFoundException
   * @throws HttpException
   * @throws NotFoundException
   * @throws ReflectionException
   */
  private function dispatch(string $path, string $rootModuleClass, string $method = 'GET'): array
  {
    MiddlewareTrace::reset();
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    $_GET['path'] = $path;

    $controllerTokensList = $this->buildControllerTokensForRootModule($rootModuleClass);
    $this->configureModuleMiddleware();
    $request = $this->makeRequest($path);
    $controller = $this->router->getActivatedController($request, $controllerTokensList);
    $response = $this->router->handleRequest($request, $controller);

    return ['controller' => $controller, 'request' => $request, 'response' => $response];
  }

  /**
   * @param class-string $rootModuleClass
   * @return array<string, ReflectionClass>
   * @throws EntryNotFoundException
   * @throws HttpException
   */
  private function buildControllerTokensForRootModule(string $rootModuleClass): array
  {
    $moduleManager = ModuleManager::getInstance();
    $controllerManager = ControllerManager::getInstance();

    $moduleManager->setRootModuleClass($rootModuleClass);
    $moduleManager->buildModuleTokensList($rootModuleClass);

    return $controllerManager->buildControllerTokensList($moduleManager->getModuleTokens());
  }

  /**
   * @return void
   * @throws ContainerException
   * @throws ReflectionException
   */
  private function configureModuleMiddleware(): void
  {
    $consumer = new MiddlewareConsumer();
    ModuleManager::getInstance()->configureMiddleware($consumer);
    $this->router->setMiddlewareConsumer($consumer);
  }

  /**
   * @param string $path
   * @return Request
   * @throws ReflectionException
   */
  private function makeRequest(string $path): Request
  {
    $_GET['path'] = $path;
    $this->resetRequestSingleton();
    $this->resetResponseSingleton();

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

  /**
   * @throws ReflectionException
   */
  private function resetResponseSingleton(): void
  {
    $responseInstanceProperty = new ReflectionProperty(Response::class, 'instance');
    $responseInstanceProperty->setValue(null, null);
  }
}
