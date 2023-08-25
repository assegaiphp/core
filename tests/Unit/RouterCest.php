<?php

namespace Tests\Unit;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Routing\Router;
use Codeception\Attribute\Incomplete;
use Mocks\MockController;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Tests\Support\UnitTester;

class RouterCest
{
  const VALID_TEST_URI = '/test';
  const INVALID_TEST_URI = '/invalid';
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

    $this->router = Router::getInstance();
    $this->controller = new MockController();
  }

  public function _after(): void
  {
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

  public function testTheHandleRequestMethod(UnitTester $I): void
  {

  }
}