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
use Mocks\ConstrainedRoutingAppModule;
use Mocks\ConstrainedUsersController;
use Mocks\InvalidConstraintAppModule;
use Mocks\LegacyAppModule;
use Mocks\MismatchedConstraintAppModule;
use Mocks\NestedApiController;
use Mocks\NestedAppModule;
use Mocks\NestedFeaturesController;
use Mocks\NestedRootController;
use Mocks\UnknownConstraintAppModule;
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
  public function testUnconstrainedParameterRoutesStillWork(UnitTester $I): void
  {
    $result = $this->dispatch('/test/12', LegacyAppModule::class);

    $I->assertInstanceOf(MockController::class, $result['controller']);
    $I->assertSame('This action returns a #12 users', $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedIntRoutesMatchNumericSegments(UnitTester $I): void
  {
    $result = $this->dispatch('/users/42', ConstrainedRoutingAppModule::class);

    $I->assertInstanceOf(ConstrainedUsersController::class, $result['controller']);
    $I->assertSame('id-42', $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedIntRoutesRejectNonNumericSegments(UnitTester $I): void
  {
    $result = $this->dispatch('/users/andrew', ConstrainedRoutingAppModule::class);

    $I->assertSame('username-andrew', $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedSlugRoutesMatchSlugLikeSegments(UnitTester $I): void
  {
    $result = $this->dispatch('/users/andrew-doe', ConstrainedRoutingAppModule::class);

    $I->assertSame('username-andrew-doe', $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedUuidRoutesMatchValidUuidSegments(UnitTester $I): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $result = $this->dispatch("/users/$uuid", ConstrainedRoutingAppModule::class);

    $I->assertSame("uuid-$uuid", $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedUuidRoutesRejectInvalidUuids(UnitTester $I): void
  {
    try {
      $this->dispatch('/tokens/not-a-uuid', ConstrainedRoutingAppModule::class);
      $I->fail('Expected the invalid UUID route to be rejected.');
    } catch (NotFoundException $exception) {
      $I->assertStringContainsString('/tokens/not-a-uuid', $exception->getMessage());
    }
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedAlphaRoutesMatchAlphabeticSegments(UnitTester $I): void
  {
    $result = $this->dispatch('/constraints/alpha/Andrew', ConstrainedRoutingAppModule::class);

    $I->assertSame('alpha-Andrew', $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedAlnumRoutesMatchAlphaNumericSegments(UnitTester $I): void
  {
    $result = $this->dispatch('/constraints/alnum/A1B2C3', ConstrainedRoutingAppModule::class);

    $I->assertSame('alnum-A1B2C3', $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedHexRoutesMatchHexSegments(UnitTester $I): void
  {
    $result = $this->dispatch('/constraints/hex/deadBEEF', ConstrainedRoutingAppModule::class);

    $I->assertSame('hex-deadBEEF', $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstrainedUlidRoutesMatchValidUlids(UnitTester $I): void
  {
    $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    $result = $this->dispatch("/constraints/ulid/$ulid", ConstrainedRoutingAppModule::class);

    $I->assertSame("ulid-$ulid", $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testAdditionalBuiltinConstraintsRejectInvalidSegments(UnitTester $I): void
  {
    $invalidPaths = [
      '/constraints/alpha/abc123',
      '/constraints/alnum/abc-123',
      '/constraints/hex/not-hex',
      '/constraints/ulid/not-a-valid-ulid',
    ];

    foreach ($invalidPaths as $path) {
      try {
        $this->dispatch($path, ConstrainedRoutingAppModule::class);
        $I->fail("Expected the invalid constrained route '$path' to be rejected.");
      } catch (NotFoundException $exception) {
        $I->assertStringContainsString($path, $exception->getMessage());
      }
    }
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testAmbiguousRoutesResolveByConstraints(UnitTester $I): void
  {
    $numericResult = $this->dispatch('/users/42', ConstrainedRoutingAppModule::class);
    $slugResult = $this->dispatch('/users/andrew', ConstrainedRoutingAppModule::class);

    $I->assertSame('id-42', $numericResult['response']->getBody());
    $I->assertSame('username-andrew', $slugResult['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testStaticRoutePrecedenceStillWinsOverDynamicRoutes(UnitTester $I): void
  {
    $result = $this->dispatch('/users/me', ConstrainedRoutingAppModule::class);

    $I->assertSame('me', $result['response']->getBody());
  }

  /**
   * @throws ReflectionException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testInvalidConstraintSyntaxFailsClearly(UnitTester $I): void
  {
    try {
      $this->dispatch('/broken/42', InvalidConstraintAppModule::class);
      $I->fail('Expected invalid constrained route syntax to throw an exception.');
    } catch (HttpException $exception) {
      $I->assertStringContainsString("Invalid constrained route segment", $exception->getMessage());
    }
  }

  /**
   * @throws ReflectionException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testUnknownConstraintsFailClearly(UnitTester $I): void
  {
    try {
      $this->dispatch('/unknown-constraint/42', UnknownConstraintAppModule::class);
      $I->fail('Expected an unknown route constraint to throw an exception.');
    } catch (HttpException $exception) {
      $I->assertStringContainsString("Unknown route constraint 'money'", $exception->getMessage());
    }
  }

  /**
   * @throws ReflectionException
   * @throws ContainerException
   * @throws EntryNotFoundException
   */
  public function testConstraintTypeMismatchesFailClearly(UnitTester $I): void
  {
    try {
      $this->dispatch('/strict/42', MismatchedConstraintAppModule::class);
      $I->fail('Expected a constrained route type mismatch to throw an exception.');
    } catch (HttpException $exception) {
      $I->assertStringContainsString("conflicts with declared PHP type 'string'", $exception->getMessage());
    }
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
    return $this->buildControllerTokensForRootModule(NestedAppModule::class);
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
   * @param string $path
   * @param class-string $rootModuleClass
   * @return array{controller: object, request: Request, response: mixed}
   * @throws ContainerException
   * @throws EntryNotFoundException
   * @throws HttpException
   * @throws NotFoundException
   * @throws ReflectionException
   */
  private function dispatch(string $path, string $rootModuleClass): array
  {
    $controllerTokensList = $this->buildControllerTokensForRootModule($rootModuleClass);
    $request = $this->makeRequest($path);
    $controller = $this->router->getActivatedController($request, $controllerTokensList);
    $response = $this->router->handleRequest($request, $controller);

    return ['controller' => $controller, 'request' => $request, 'response' => $response];
  }
}
