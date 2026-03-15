<?php

namespace Assegai\Core\Routing;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Head;
use Assegai\Core\Attributes\Http\Options;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Put;
use Assegai\Core\Attributes\Http\Query;
use Assegai\Core\Attributes\Http\Sse;
use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\Req;
use Assegai\Core\Attributes\Res;
use Assegai\Core\Attributes\UseGuards;
use Assegai\Core\Attributes\UseInterceptors;
use Assegai\Core\Consumers\GuardsConsumer;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\ControllerManager;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\ForbiddenException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Exceptions\InterceptorException;
use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Interceptors\InterceptorsConsumer;
use Assegai\Core\Interfaces\IOnGuard;
use Assegai\Core\Interfaces\IPipeTransform;
use Assegai\Core\Interfaces\MiddlewareInterface;
use Assegai\Core\ModuleManager;
use Assegai\Core\Util\TypeManager;
use Assegai\Core\Util\Validator;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionUnionType;
use stdClass;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * The Router class is responsible for routing incoming requests to the appropriate controller and handler.
 *
 * @package Assegai\Core\Routing
 */
final class Router
{
  private const array ROUTE_CONSTRAINT_PATTERNS = [
    'int' => '/^-?\d+$/',
    'slug' => '/^[A-Za-z][A-Za-z0-9_-]*$/',
    'uuid' => '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
    'alpha' => '/^[A-Za-z]+$/',
    'alnum' => '/^[A-Za-z0-9]+$/',
    'hex' => '/^[A-Fa-f0-9]+$/',
    'ulid' => '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i',
  ];
  private const int STATIC_ROUTE_PRIORITY = 300;
  private const int CONSTRAINED_DYNAMIC_ROUTE_PRIORITY = 200;
  private const int DYNAMIC_ROUTE_PRIORITY = 100;
  private const int WILDCARD_ROUTE_PRIORITY = -1;

  /**
   * @var Router|null The Router instance.
   */
  private static ?Router $instance = null;
  /**
   * @var Injector The injector instance.
   */
  private Injector $injector;
  /**
   * @var GuardsConsumer The guards consumer instance.
   */
  private GuardsConsumer $guardsConsumer;
  /**
   * @var InterceptorsConsumer The interceptors consumer instance.
   */
  private InterceptorsConsumer $interceptorsConsumer;
  /**
   * @var ControllerManager The controller manager instance.
   */
  private ControllerManager $controllerManager;
  /**
   * @var ModuleManager The module manager instance.
   */
  private ModuleManager $moduleManager;
  /**
   * @var array The global pipes.
   */
  private array $globalPipes = [];
  /**
   * @var array The global interceptors.
   */
  private array $globalInterceptors = [];
  /**
   * @var array<class-string|ExceptionFilterInterface> The global filters.
   */
  private array $globalFilters = [];
  private ?MiddlewareConsumer $middlewareConsumer = null;

  private final function __construct()
  {
    $this->injector = Injector::getInstance();
    $this->interceptorsConsumer = InterceptorsConsumer::getInstance();
    $this->guardsConsumer = GuardsConsumer::getInstance();
    $this->controllerManager = ControllerManager::getInstance();
    $this->moduleManager = ModuleManager::getInstance();
  }

  /**
   * @return Router
   */
  public static function getInstance(): Router
  {
    if (!self::$instance) {
      self::$instance = new Router();
    }

    return self::$instance;
  }

  /**
   * @return Request
   */
  public function getRequest(): Request
  {
    return Request::getInstance();
  }

  /**
   * Sets the middleware consumer that should be consulted before handler execution.
   *
   * @param MiddlewareConsumer|null $middlewareConsumer
   * @return void
   */
  public function setMiddlewareConsumer(?MiddlewareConsumer $middlewareConsumer): void
  {
    $this->middlewareConsumer = $middlewareConsumer;
  }

  /**
   * Determines the controller to be activated based on the given request.
   *
   * @param Request $request The request to be processed.
   * @param ReflectionClass[] $controllerTokensList A list of controller reflection instances.
   * @return object The activated controller.
   * @throws ContainerException If there was an error during dependency injection.
   * @throws NotFoundException
   * @throws ReflectionException
   * @throws HttpException
   */
  public function getActivatedController(Request $request, array $controllerTokensList): object
  {
    $rootController = null;

    foreach ($controllerTokensList as $reflectionController) {
      if ($this->isRootController($reflectionController)) {
        $rootController = $reflectionController;
        break;
      }
    }

    $activatedController = $this->getActivatedControllerToken(
      request: $request,
      moduleClass: $this->moduleManager->getRootModuleClass(),
      fallbackController: $rootController,
    );

    if (is_null($activatedController)) {
      throw new NotFoundException(path: $request->getPath());
    }

    return $this->activateController($activatedController);
  }

    /**
     * Determines if the given controller can be activated.
     *
     * @param ReflectionClass $reflectionController The reflection instance of the controller to be activated.
     * @param Request $request
     * @return bool True if the controller can be activated, false otherwise.
     */
  private function canActivateController(ReflectionClass $reflectionController, Request $request): bool
  {
    return $this->getControllerMatchScore($reflectionController, $request) >= 0;
  }

  /**
   * Activates the given controller.
   *
   * @param ReflectionClass $reflectionController The reflection instance of the controller to be activated.
   * @return object Returns an instance of the activated controller
   * @throws ReflectionException
   */
  private function activateController(ReflectionClass $reflectionController): object
  {
    $dependencies = [];

    if ($constructor = $reflectionController->getConstructor()) {
      $constructorParams = $constructor->getParameters();

      # Instantiate attributes
      $controllerReflectionAttributes = $reflectionController->getAttributes();
      $controllerAttributes = [];

      foreach ($controllerReflectionAttributes as $controllerAttribute) {
        $controllerAttributes[] = $controllerAttribute->newInstance();
      }

      foreach ($constructorParams as $param) {
        try {
          $dependencies[] = $this->injector->resolve($param->getType()->getName());
        } catch (Exception $exception) {
          exit(var_export([
            'exception' => $exception,
            'controllerAttributes' => $controllerAttributes,
            'dependencies' => $dependencies,
            'param' => $param,
            'param-type' => $param->getType()->getName(),
          ], true));
        }
      }
    }

    return $reflectionController->newInstanceArgs($dependencies);
  }

  /**
   * @param object $controller
   * @return ReflectionMethod[] Returns a list of handlers belonging to the given controller
   */
  public function getControllerHandlers(object $controller): array
  {
    $handlers = [];
    $reflectionClass = new ReflectionClass($controller);
    $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach($reflectionMethods as $reflectionMethod) {
      if ($this->isValidHandler($reflectionMethod)) {
        $handlers[] = $reflectionMethod;
      }
    }

    return $handlers;
  }

  /**
   * @param ReflectionMethod[] $handlers
   * @param object $controller
   * @param Request $request
   * @return ReflectionMethod|null
   */
  public function getActivatedHandler(array $handlers, object $controller, Request $request): ?ReflectionMethod
  {
    $bestHandler = null;
    $bestMatch = null;

    foreach ($handlers as $handler) {
      $matchData = $this->getHandlerMatchData(handler: $handler, controller: $controller, request: $request);

      if (is_null($matchData)) {
        continue;
      }

      if (is_null($bestMatch) || $this->isBetterRouteMatch($matchData, $bestMatch)) {
        $bestHandler = $handler;
        $bestMatch = $matchData;
      }
    }

    if ($bestHandler && $bestMatch) {
      $request->setParams($bestMatch['params']);
      $this->validateMatchedRouteConstraints($bestHandler, $bestMatch['constraints']);
      $this->parseHandlerAttributes($bestHandler);
      return $bestHandler;
    }

    $request->clearParams();

    return null;
  }

  /**
   * @param ReflectionMethod $method
   * @return bool
   */
  public function isValidHandler(ReflectionMethod $method): bool
  {
    $attributes = $method->getAttributes();

    $foundRequestMapperAttribute = false;
    foreach ($attributes as $attribute) {
      if (Validator::isValidRequestMapperAttribute($attribute)) {
        $foundRequestMapperAttribute = true;
      }
    }

    return $foundRequestMapperAttribute;
  }

  /**
   * @param ReflectionMethod $handler
   * @param object $controller
   * @param Request $request
   * @return bool
   */
  public function canActivateHandler(ReflectionMethod $handler, object $controller, Request $request): bool
  {
    return !is_null($this->getHandlerMatchData(handler: $handler, controller: $controller, request: $request));
  }

  /**
   * Handles an incoming client request using the given controller.
   *
   * @param Request $request The request to be processed.
   * @param object $controller The controller to handle the request.
   * @return Response The response to be sent back to the client.
   * @throws ContainerException If there was an error during dependency injection.
   * @throws EntryNotFoundException If a dependency was not found in the DI container.
   * @throws ForbiddenException If the request is forbidden.
   * @throws HttpException If there was an error processing the request.
   * @throws NotFoundException If the requested resource could not be found.
   * @throws ReflectionException If there was an error processing a reflection.
   */
  public function handleRequest(Request $request, object $controller): Response
  {
    $handlers = $this->getControllerHandlers(controller: $controller);
    $activatedHandler = $this->getActivatedHandler(handlers: $handlers, controller: $controller, request: $request);
    $response = Response::getInstance();

    if (!$activatedHandler) {
      throw new NotFoundException($request->getPath());
    }

    $handledResponse = $response;
    $shouldContinue = $this->runMiddleware(
      request: $request,
      response: $response,
      next: function () use ($request, $controller, $activatedHandler, &$handledResponse, $response): void {
        $handledResponse = $this->handleActivatedRoute($request, $controller, $activatedHandler, $response);
      }
    );

    $finalResponse = $shouldContinue ? $handledResponse : $response;

    return clone $finalResponse;
  }

  /**
   * Runs the configured middleware chain for the current request.
   *
   * @param Request $request
   * @param Response $response
   * @param callable $next
   * @return bool True when the request should continue to the handler pipeline.
   * @throws ContainerException
   * @throws ReflectionException
   * @throws HttpException
   */
  private function runMiddleware(Request $request, Response $response, callable $next): bool
  {
    if (!$this->middlewareConsumer) {
      $next();
      return true;
    }

    $middleware = $this->middlewareConsumer->getMiddlewareForRequest($request);

    if (!$middleware) {
      $next();
      return true;
    }

    return $this->executeMiddlewareStack($middleware, $request, $response, $next);
  }

  /**
   * Executes the current middleware stack recursively so each middleware controls whether the chain continues.
   *
   * @param array<int, MiddlewareInterface|callable|class-string<MiddlewareInterface>> $middleware
   * @param Request $request
   * @param Response $response
   * @param callable $destination
   * @param int $index
   * @return bool
   * @throws ContainerException
   * @throws ReflectionException
   * @throws HttpException
   */
  private function executeMiddlewareStack(
    array $middleware,
    Request $request,
    Response $response,
    callable $destination,
    int $index = 0,
  ): bool
  {
    if (!isset($middleware[$index])) {
      $destination();
      return true;
    }

    $shouldContinue = false;
    $next = function () use (&$shouldContinue, $middleware, $request, $response, $destination, $index): bool {
      $shouldContinue = $this->executeMiddlewareStack($middleware, $request, $response, $destination, $index + 1);

      return $shouldContinue;
    };

    $this->invokeMiddleware($middleware[$index], $request, $response, $next);

    return $shouldContinue;
  }

  /**
   * Invokes the given middleware definition, resolving class strings through the injector when possible.
   *
   * @param MiddlewareInterface|callable|class-string<MiddlewareInterface> $middleware
   * @param Request $request
   * @param Response $response
   * @param callable $next
   * @return void
   * @throws ContainerException
   * @throws ReflectionException
   * @throws HttpException
   */
  private function invokeMiddleware(
    object|string $middleware,
    Request $request,
    Response $response,
    callable $next,
  ): void
  {
    if ($middleware instanceof MiddlewareInterface) {
      $middleware->use($request, $response, $next);
      return;
    }

    if (is_string($middleware) && !class_exists($middleware) && is_callable($middleware)) {
      $middleware($request, $response, $next);
      return;
    }

    if (is_string($middleware)) {
      $middleware = $this->resolveMiddlewareInstance($middleware);
    }

    if ($middleware instanceof MiddlewareInterface) {
      $middleware->use($request, $response, $next);
      return;
    }

    if (is_callable($middleware)) {
      $middleware($request, $response, $next);
      return;
    }

    throw new HttpException('Configured middleware must implement MiddlewareInterface or be callable.');
  }

  /**
   * Resolves a middleware class into an executable instance.
   *
   * @param class-string<MiddlewareInterface> $middlewareClass
   * @return object
   * @throws ContainerException
   * @throws ReflectionException
   */
  private function resolveMiddlewareInstance(string $middlewareClass): object
  {
    try {
      $resolved = $this->injector->resolve($middlewareClass);

      if ($resolved instanceof MiddlewareInterface || is_callable($resolved)) {
        return $resolved;
      }
    } catch (ContainerException) {
      // Fall back to direct instantiation for lightweight middleware classes.
    }

    $reflectionClass = new ReflectionClass($middlewareClass);
    $constructor = $reflectionClass->getConstructor();

    if (!$constructor || !$constructor->getParameters()) {
      return $reflectionClass->newInstance();
    }

    $dependencies = [];

    foreach ($constructor->getParameters() as $parameter) {
      $type = $parameter->getType();

      if (!$type || $type instanceof ReflectionUnionType || $type->isBuiltin()) {
        $dependencies[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        continue;
      }

      $dependencies[] = $this->injector->resolve($type->getName());
    }

    return $reflectionClass->newInstanceArgs($dependencies);
  }

  /**
   * Continues the request pipeline once middleware has allowed the request through.
   *
   * @param Request $request
   * @param object $controller
   * @param ReflectionMethod $activatedHandler
   * @param Response $response
   * @return Response
   * @throws ContainerException
   * @throws EntryNotFoundException
   * @throws ForbiddenException
   * @throws HttpException
   * @throws ReflectionException
   */
  private function handleActivatedRoute(
    Request $request,
    object $controller,
    ReflectionMethod $activatedHandler,
    Response $response,
  ): Response
  {
    $controllerReflection = new ReflectionClass($controller);
    $context = $this->createContext(class: $controllerReflection, handler: $activatedHandler);

    # Consume controller guards
    $this->consumeControllerGuards($controllerReflection, $controller, $context);

    # Consume controller interceptors
    $controllerInterceptorCallHandlers = $this->consumeControllerInterceptors($controllerReflection, $context);

    # Consume handler guards
    $useGuardsAttributes = $activatedHandler->getAttributes(UseGuards::class);

    if ($useGuardsAttributes) {
      /** @var UseGuards $handlerUseGuardsAttribute */
      $handlerUseGuardsAttribute = $useGuardsAttributes[0]->newInstance();

      if (! $this->guardsConsumer->canActivate(guards: $handlerUseGuardsAttribute->guards, context: $context) ) {
        throw new $handlerUseGuardsAttribute->exceptionClassName();
      }
    }

    # Consume handler interceptors
    $handlerInterceptorCallHandlers = [];
    $useInterceptorsAttributes = $activatedHandler->getAttributes(UseInterceptors::class);

    if ($useInterceptorsAttributes) {
      /** @var UseInterceptors $handlerUseInterceptorsInstance */
      $handlerUseInterceptorsInstance = $useInterceptorsAttributes[0]->newInstance();

      $handlerInterceptorCallHandlers =
        $this->interceptorsConsumer
          ->intercept(
            interceptors: $handlerUseInterceptorsInstance->interceptorsList,
            context: $context
          );
    }

    # Resolve handler parameters
    $dependencies = $this->resolveHandlerParameters($activatedHandler, $request);

    $result = $activatedHandler->invokeArgs($controller, $dependencies);

    if ($result instanceof Response) {
      return $result;
    }

    if (is_null($result)) {
      $result = [];
    }
    $context->switchToHttp()->getResponse()->setBody($result);

    # Run handler Interceptors
    /** @var callable $handler */
    foreach ($handlerInterceptorCallHandlers as $handler) {
      /** @var ExecutionContext $context */
      $context = $handler($context);
    }

    # Run controller Interceptors
    /** @var callable $handler */
    foreach ($controllerInterceptorCallHandlers as $handler) {
      /** @var ExecutionContext $context */
      $context = $handler($context);
    }

    return match(true) {
      $context instanceof ExecutionContext => $context->switchToHttp()->getResponse(),
      $context instanceof Response => $context,
      default => $response
    };
  }

  /**
   * Adds global pipes to the router.
   *
   * @param array $pipes The list of global pipes to be added.
   * @return void
   */
  public function addGlobalPipes(array $pipes): void
  {
    $this->globalPipes = [...$this->globalPipes, ...$pipes];
  }

  /**
   * Adds global interceptors to the router.
   *
   * @param array $interceptors The list of global interceptors to be added.
   * @return void
   */
  public function addGlobalInterceptors(array $interceptors): void
  {
    $this->globalInterceptors = [...$this->globalInterceptors, ...$interceptors];
  }

  public function addGlobalFilters(array $filters): void
  {
    $this->globalFilters = [...$this->globalFilters, ...$filters];
  }

  /**
   * Redirects the client to the given URL.
   *
   * @param string $url The URL to redirect the client to.
   * @param int|null $statusCode The status code to be used for the redirect.
   * @return never
   * @throws HttpException If the HTTP status code could not be set.
   */
  public static function redirectTo(string $url, ?int $statusCode = null): never
  {
    if ($statusCode) {
      $code = $statusCode;
      if (false === http_response_code($code)) {
        throw new HttpException("Failed to set HTTP status code to $code");
      }
    }
    header("Content-Type: text/html");
    exit(<<<HTML
      <script>
        window.location.href = "$url";
</script>
HTML);
  }

  /**
   * Returns the path prefix for the given controller.
   * @param object $controller The controller to get the path prefix for.
   * @return string The path prefix for the given controller.
   */
  private function getControllerPrefix(object $controller): string
  {
    $reflectionController = $controller instanceof ReflectionClass ? $controller : new ReflectionClass($controller);
    $resolvedPrefix = $this->controllerManager->getResolvedControllerPath($reflectionController->getName());

    if ($resolvedPrefix) {
      return $resolvedPrefix;
    }

    $attributes = $reflectionController->getAttributes(Controller::class);

    if (!$attributes) {
      $attributes = $reflectionController->getAttributes(\Assegai\Attributes\Controller::class);
    }

    foreach ($attributes as $attribute) {
      $instance = $attribute->newInstance();
      return $this->normalizePath($instance->path ?? '/');
    }

    return '/';
  }

  /**
   * @param ReflectionMethod $handler
   * @return string
   */
  private function getHandlerPath(ReflectionMethod $handler): string
  {
    # TODO: Filter by Request class
    /*
     * There is need to create a Request base class from which all HTTP verb methods inherit
     */
    $attributes = $handler->getAttributes();
    foreach ($attributes as $attribute) {
      return $attribute->newInstance()->path;
    }

    return '';
  }

  /**
   * Returns the regular expression pattern for matching the given path.
   *
   * @param string $path The path to be matched.
   * @return string The regular expression pattern for matching the given path.
   */
  private function getPathMatchingPattern(string $path): string
  {
    // Remove trailing slash if it exists
    if (str_ends_with($path, '/')) {
      $path = rtrim($path, '/');
    }

    // Replace `*` with `.+` to match any character one or more times
    $path = str_replace('*', '.+', $path);

    // Replace named placeholders with regex pattern to match any word characters one or more times
    return preg_replace(pattern: '/(\/?):\w+/', replacement: '$1([\w-]+)', subject: $path);
  }

  /**
   * Returns route match metadata for a handler candidate when it matches the incoming request.
   *
   * @param ReflectionMethod $handler
   * @param object $controller
   * @param Request $request
   * @return array{constraints: array<string, string>, matched_segments: int, params: array<int|string, string>, route_length: int, specificity: int}|null
   * @throws HttpException
   * @throws ReflectionException
   */
  private function getHandlerMatchData(ReflectionMethod $handler, object $controller, Request $request): ?array
  {
    $path = $this->normalizePath($request->getPath());
    $controllerPrefix = $this->getControllerPrefix(controller: $controller);
    $handlerPath = $this->getHandlerPath(handler: $handler);
    $controllerParams = $this->matchRoutePath(route: $controllerPrefix, path: $path, allowPartial: true);
    $remainingPath = $this->getRemainingPath(path: $path, prefix: $controllerPrefix);
    $handlerParams = $this->matchRoutePath(route: $handlerPath, path: $remainingPath);
    $attributes = $handler->getAttributes();

    if (empty($attributes)) {
      return null;
    }

    $foundPathMatch = !is_null($controllerParams) && !is_null($handlerParams);

    if ($foundPathMatch === false && $handler->getShortName() === trim($remainingPath, '/')) {
      $foundPathMatch = true;
      $handlerParams = [];
    }

    if (!$foundPathMatch) {
      return null;
    }

    $requestMapperClassFound = false;

    foreach ($attributes as $attribute) {
      switch($request->getMethod()) {
        case RequestMethod::OPTIONS:
          if ($attribute->getName() === Options::class) {
            $requestMapperClassFound = true;
          }
          break;

        case RequestMethod::GET:
          if ($attribute->getName() === Get::class || $attribute->getName() === Sse::class) {
            $requestMapperClassFound = true;
          }
          break;

        case RequestMethod::POST:
          if ($attribute->getName() === Post::class) {
            $requestMapperClassFound = true;
          }
          break;

        case RequestMethod::PUT:
          if ($attribute->getName() === Put::class) {
            $requestMapperClassFound = true;
          }
          break;

        case RequestMethod::PATCH:
          if ($attribute->getName() === Patch::class) {
            $requestMapperClassFound = true;
          }
          break;

        case RequestMethod::DELETE:
          if ($attribute->getName() === Delete::class) {
            $requestMapperClassFound = true;
          }
          break;

        case RequestMethod::HEAD:
          if ($attribute->getName() === Head::class) {
            $requestMapperClassFound = true;
          }
          break;
      }
    }

    if (!$requestMapperClassFound) {
      return null;
    }

    $handlerRoute = $this->combinePaths($controllerPrefix, $handlerPath);

    return [
      'constraints' => array_merge(
        $this->getRouteConstraintDefinitions($controllerPrefix),
        $this->getRouteConstraintDefinitions($handlerPath),
      ),
      'matched_segments' => count($this->getPathSegments($handlerRoute)),
      'params' => array_merge($controllerParams ?? [], $handlerParams ?? []),
      'route_length' => strlen($handlerRoute),
      'specificity' => $this->getRouteSpecificityScore($handlerRoute),
    ];
  }

  /**
   * Matches a route template against a request path and returns extracted params on success.
   *
   * @param string $route
   * @param string $path
   * @param bool $allowPartial
   * @return array<int|string, string>|null
   */
  private function matchRoutePath(string $route, string $path, bool $allowPartial = false): ?array
  {
    $routeSegments = $this->getPathSegments($route);
    $pathSegments = $this->getPathSegments($path);

    if (empty($routeSegments)) {
      return ($allowPartial || empty($pathSegments)) ? [] : null;
    }

    $params = [];
    $paramIndex = 0;

    foreach ($routeSegments as $index => $routeSegment) {
      $segmentMeta = $this->parseRouteSegment($routeSegment);

      if ($segmentMeta['type'] === 'wildcard') {
        return $params;
      }

      if (!isset($pathSegments[$index])) {
        return null;
      }

      $pathSegment = $pathSegments[$index];

      if ($segmentMeta['type'] === 'dynamic') {
        if (
          !empty($segmentMeta['constraint']) &&
          !$this->matchesRouteConstraint($segmentMeta['constraint'], $pathSegment)
        ) {
          return null;
        }

        $key = $segmentMeta['name'];
        $params[$paramIndex++] = $pathSegment;
        $params[$key] = $pathSegment;
        continue;
      }

      if ($segmentMeta['value'] !== $pathSegment) {
        return null;
      }
    }

    if (!$allowPartial && count($pathSegments) !== count($routeSegments)) {
      return null;
    }

    return $params;
  }

  /**
   * Parses a route segment and returns its matching metadata.
   *
   * Supported constrained dynamic syntax uses angle brackets, for example `:id<int>` or `:slug<uuid>`.
   *
   * @param string $segment
   * @return array{constraint: string|null, name: string|null, type: string, value: string}
   * @throws HttpException
   */
  private function parseRouteSegment(string $segment): array
  {
    if ($segment === '*') {
      return ['constraint' => null, 'name' => null, 'type' => 'wildcard', 'value' => $segment];
    }

    if (!str_starts_with($segment, ':')) {
      return ['constraint' => null, 'name' => null, 'type' => 'static', 'value' => $segment];
    }

    if (!preg_match('/^:(?<name>[A-Za-z_][A-Za-z0-9_]*)(?:<(?<constraint>[A-Za-z][A-Za-z0-9_]*)>)?$/', $segment, $matches)) {
      throw new HttpException(
        "Invalid constrained route segment '$segment'. Use ':name' or ':name<constraint>'."
      );
    }

    $constraint = $matches['constraint'] ?? null;

    if ($constraint && !array_key_exists($constraint, self::ROUTE_CONSTRAINT_PATTERNS)) {
      throw new HttpException("Unknown route constraint '$constraint' in segment '$segment'.");
    }

    return [
      'constraint' => $constraint ?: null,
      'name' => $matches['name'],
      'type' => 'dynamic',
      'value' => $segment,
    ];
  }

  /**
   * Determines if a path segment satisfies the named built-in route constraint.
   *
   * @param string $constraint
   * @param string $value
   * @return bool
   */
  private function matchesRouteConstraint(string $constraint, string $value): bool
  {
    return preg_match(self::ROUTE_CONSTRAINT_PATTERNS[$constraint], $value) === 1;
  }

  /**
   * Returns the constrained params declared within a route template.
   *
   * @param string $route
   * @return array<string, string>
   * @throws HttpException
   */
  private function getRouteConstraintDefinitions(string $route): array
  {
    $definitions = [];

    foreach ($this->getPathSegments($route) as $segment) {
      $segmentMeta = $this->parseRouteSegment($segment);

      if ($segmentMeta['type'] === 'dynamic' && !empty($segmentMeta['constraint'])) {
        $definitions[$segmentMeta['name']] = $segmentMeta['constraint'];
      }
    }

    return $definitions;
  }

  /**
   * Calculates a specificity score where static segments outrank constrained params, which outrank unconstrained params.
   *
   * @param string $route
   * @return int
   * @throws HttpException
   */
  private function getRouteSpecificityScore(string $route): int
  {
    $score = 0;

    foreach ($this->getPathSegments($route) as $segment) {
      $segmentMeta = $this->parseRouteSegment($segment);

      $score += match (true) {
        $segmentMeta['type'] === 'wildcard' => self::WILDCARD_ROUTE_PRIORITY,
        $segmentMeta['type'] === 'static' => self::STATIC_ROUTE_PRIORITY,
        !empty($segmentMeta['constraint']) => self::CONSTRAINED_DYNAMIC_ROUTE_PRIORITY,
        default => self::DYNAMIC_ROUTE_PRIORITY,
      };
    }

    return $score;
  }

  /**
   * Returns the request path remainder after removing the resolved controller prefix.
   *
   * @param string $path
   * @param string $prefix
   * @return string
   */
  private function getRemainingPath(string $path, string $prefix): string
  {
    $pathSegments = $this->getPathSegments($path);
    $prefixSegments = $this->getPathSegments($prefix);
    $wildcardIndex = array_search('*', $prefixSegments, true);
    $consumedSegments = ($wildcardIndex === false) ? count($prefixSegments) : count($pathSegments);
    $remainingSegments = array_slice($pathSegments, $consumedSegments);

    return empty($remainingSegments) ? '/' : '/' . implode('/', $remainingSegments);
  }

  /**
   * Determines whether a candidate route match is more specific than the current best match.
   *
   * @param array{matched_segments: int, route_length: int, specificity: int} $candidate
   * @param array{matched_segments: int, route_length: int, specificity: int} $currentBest
   * @return bool
   */
  private function isBetterRouteMatch(array $candidate, array $currentBest): bool
  {
    if ($candidate['specificity'] > $currentBest['specificity']) {
      return true;
    }

    if ($candidate['specificity'] < $currentBest['specificity']) {
      return false;
    }

    if ($candidate['matched_segments'] > $currentBest['matched_segments']) {
      return true;
    }

    if ($candidate['matched_segments'] < $currentBest['matched_segments']) {
      return false;
    }

    return $candidate['route_length'] > $currentBest['route_length'];
  }

  /**
   * Determines which controller token should be activated within the current module branch.
   *
   * @param Request $request
   * @param string $moduleClass
   * @param ReflectionClass|null $fallbackController
   * @return ReflectionClass|null
   * @throws HttpException
   * @throws ReflectionException
   */
  private function getActivatedControllerToken(
    Request $request,
    string $moduleClass,
    ?ReflectionClass $fallbackController = null
  ): ?ReflectionClass
  {
    $bestMatch = $fallbackController;

    foreach ($this->controllerManager->getModuleControllerTokens($moduleClass) as $reflectionController) {
      if (!$this->canActivateController($reflectionController, $request)) {
        continue;
      }

      $bestMatch = $this->preferControllerMatch($request, $bestMatch, $reflectionController);
    }

    foreach ($this->moduleManager->getImportedModules($moduleClass) as $importedModuleClass) {
      if (!$this->requestMatchesModuleBranch($request, $importedModuleClass)) {
        continue;
      }

      $branchMatch = $this->getActivatedControllerToken(
        request: $request,
        moduleClass: $importedModuleClass,
        fallbackController: $bestMatch,
      );

      $bestMatch = $this->preferControllerMatch($request, $bestMatch, $branchMatch);
    }

    return $bestMatch;
  }

  /**
   * Chooses the more specific controller match for the current request.
   *
   * @param Request $request
   * @param ReflectionClass|null $currentBest
   * @param ReflectionClass|null $candidate
   * @return ReflectionClass|null
   */
  private function preferControllerMatch(
    Request $request,
    ?ReflectionClass $currentBest,
    ?ReflectionClass $candidate
  ): ?ReflectionClass
  {
    if (is_null($candidate)) {
      return $currentBest;
    }

    if (is_null($currentBest)) {
      return $candidate;
    }

    $candidateRoute = $this->getControllerPrefix($candidate);
    $currentBestRoute = $this->getControllerPrefix($currentBest);
    $candidateMatch = [
      'matched_segments' => $this->getControllerMatchScore($candidate, $request),
      'route_length' => strlen($candidateRoute),
      'specificity' => $this->getRouteSpecificityScore($candidateRoute),
    ];
    $currentBestMatch = [
      'matched_segments' => $this->getControllerMatchScore($currentBest, $request),
      'route_length' => strlen($currentBestRoute),
      'specificity' => $this->getRouteSpecificityScore($currentBestRoute),
    ];

    if ($this->isBetterRouteMatch($candidateMatch, $currentBestMatch)) {
      return $candidate;
    }

    return $currentBest;
  }

  /**
   * Returns the number of request URI segments matched by the controller prefix, or `-1` when it does not match.
   *
   * @param ReflectionClass $reflectionController
   * @param Request $request
   * @return int
   */
  private function getControllerMatchScore(ReflectionClass $reflectionController, Request $request): int
  {
    $controllerPrefix = $this->getControllerPrefix($reflectionController);
    $match = $this->matchRoutePath(route: $controllerPrefix, path: $request->getPath(), allowPartial: true);

    if (is_null($match)) {
      return -1;
    }

    return count($this->getPathSegments($controllerPrefix));
  }

  /**
   * Determines if the current request can continue down the imported module branch.
   *
   * @param Request $request
   * @param string $moduleClass
   * @return bool
   */
  private function requestMatchesModuleBranch(Request $request, string $moduleClass): bool
  {
    return !is_null(
      $this->matchRoutePath(
        route: $this->controllerManager->getModuleBranchPrefix($moduleClass),
        path: $request->getPath(),
        allowPartial: true,
      )
    );
  }

  /**
   * Determines if the given pattern matches the given path.
   *
   * @param string $pattern The pattern to be matched.
   * @param string $path The path to be matched.
   * @return bool True if the pattern matches the path, false otherwise.
   */
  private function patternMatchesPath(string $pattern, string $path): bool
  {
    $path = preg_replace('/^\//', '', $path);
    $pattern = str_replace('/', '\/?', $pattern);
    $result = preg_match("/^$pattern\/?$/", $path);

    return boolval($result) === true;
  }

  /**
   * Joins path fragments into a normalized absolute route path.
   *
   * @param string ...$paths
   * @return string
   */
  private function combinePaths(string ...$paths): string
  {
    $segments = [];

    foreach ($paths as $path) {
      $normalized = trim($path);

      if ($normalized === '' || $normalized === '/') {
        continue;
      }

      foreach (explode('/', trim($normalized, '/')) as $segment) {
        if ($segment === '') {
          continue;
        }

        $segments[] = $segment;
      }
    }

    return empty($segments) ? '/' : '/' . implode('/', $segments);
  }

  /**
   * Normalizes a route path to a leading-slash form.
   *
   * @param string $path
   * @return string
   */
  private function normalizePath(string $path): string
  {
    $trimmedPath = trim($path);

    if ($trimmedPath === '' || $trimmedPath === '/') {
      return '/';
    }

    return '/' . trim($trimmedPath, '/');
  }

  /**
   * Splits the given path into URI segments for prefix comparisons.
   *
   * @param string $path
   * @return string[]
   */
  private function getPathSegments(string $path): array
  {
    $normalizedPath = trim($path, '/');

    if ($normalizedPath === '') {
      return [];
    }

    return array_values(array_filter(explode('/', $normalizedPath), static fn(string $segment) => $segment !== ''));
  }

  /**
   * @param ReflectionMethod $activatedHandler A reflection instance of the handler method to be parsed.
   * @return void
   */
  private function parseHandlerAttributes(ReflectionMethod $activatedHandler): void
  {
    $reflectionAttributes = $activatedHandler->getAttributes();
    foreach ($reflectionAttributes as $attribute) {
      $attribute->newInstance();
    }
  }

  /**
   * @param ReflectionClass $class
   * @param ReflectionMethod $handler
   * @return ExecutionContext
   */
  private function createContext(ReflectionClass $class, ReflectionMethod $handler): ExecutionContext
  {
    return new ExecutionContext(class: $class, handler: $handler);
  }

  /**
   * Consumes the guards for the given controller.
   *
   * @param ReflectionClass $controllerReflection The reflection instance of the controller.
   * @param object $controller The controller instance.
   * @param ExecutionContext $context The execution context.
   * @return void
   * @throws ForbiddenException If the request is forbidden.
   */
  private function consumeControllerGuards(ReflectionClass $controllerReflection, object $controller, ExecutionContext $context): void
  {
    $useGuardsAttributes = $controllerReflection->getAttributes(UseGuards::class);
    if ($useGuardsAttributes) {
      /** @var UseGuards $controllerUseGuardsInstance */
      $controllerUseGuardsInstance = $useGuardsAttributes[0]->newInstance();

      if (! $this->guardsConsumer->canActivate(guards: $controllerUseGuardsInstance->guards, context: $context) ) {
        if ($controller instanceof IOnGuard) {
          $controller->onGuard(context: $context);
        } else {
          throw new $controllerUseGuardsInstance->exceptionClassName();
        }
      }
    }
  }

  /**
   * Consumes the interceptors for the given controller.
   *
   * @param ReflectionClass $controllerReflection The reflection instance of the controller.
   * @param ExecutionContext $context The execution context.
   * @return InterceptorsConsumer[] Returns a list of interceptor call handlers.
   * @throws ReflectionException
   * @throws InterceptorException
   */
  private function consumeControllerInterceptors(
    ReflectionClass $controllerReflection,
    ExecutionContext $context
  ): array
  {
    $controllerInterceptorCallHandlers = [];

    if ($this->globalInterceptors) {
      $useInterceptorInstance = new UseInterceptors(interceptors: $this->globalInterceptors);

      $controllerInterceptorCallHandlers = $this->interceptorsConsumer->intercept(
        interceptors: $useInterceptorInstance->interceptorsList,
        context: $context
      );
    }

    $useInterceptorsAttributes = $controllerReflection->getAttributes(UseInterceptors::class);

    if ($useInterceptorsAttributes) {
      /** @var UseInterceptors $controllerUseInterceptorsInstance */
      $controllerUseInterceptorsInstance = $useInterceptorsAttributes[0]->newInstance();

      $controllerInterceptorCallHandlers = [...$controllerInterceptorCallHandlers,
        ...$this->interceptorsConsumer
          ->intercept(
            interceptors: $controllerUseInterceptorsInstance->interceptorsList,
            context: $context
          )
      ];
    }

    return $controllerInterceptorCallHandlers;
  }

  /**
   * Resolves the dependencies for the given handler.
   *
   * @param ReflectionMethod $activatedHandler The reflection instance of the handler method.
   * @param Request $request The request to be processed.
   * @return array<int|string, mixed> The resolved dependencies for the handler.
   * @throws ContainerException If there was an error during dependency injection.
   * @throws EntryNotFoundException If a dependency was not found in the DI container.
   * @throws HttpException If there was an error processing the request.
   * @throws ReflectionException If there was an error processing a reflection.
   */
  private function resolveHandlerParameters(ReflectionMethod $activatedHandler, Request $request): array
  {
    $dependencies = [];

    $params = $activatedHandler->getParameters();

    foreach ($params as $param) {
      $paramIsUnionType = $param->getType() instanceof ReflectionUnionType;
      $paramAttributeReflections = $param->getAttributes();

      $paramTypeName = match (true) {
        $paramIsUnionType => $param->getType()->getTypes()[0]->getName(),
        !is_null($param->getType()) => $param->getType()->getName(),
        default => 'stdClass'
      };
      $isStandardClassType = is_subclass_of($paramTypeName, stdClass::class) || $paramTypeName === 'stdClass';

      $dependency = match(true) {
        $paramIsUnionType => match(true) {
          $param->getType()->getTypes()[0]->isBuiltin(),
          $param->getType()->getTypes()[1]->isBuiltin() => $this->injector->resolveBuiltIn($param, $request, true),
        },
        $param->getType()?->isBuiltin(),
        $isStandardClassType => $this->injector->resolveBuiltIn($param, $request),
        default => $this->injector->resolve($paramTypeName, $paramAttributeReflections)
      };

      $dependency = $this->bindRequestHandlerAttributes($param, $dependency, $request);
      $dependencies[] = $this->resolveRouteParameterFallback($param, $dependency, $request);
    }

    return $dependencies;
  }

  /**
   * Determines if the given controller is the root controller.
   *
   * @param ReflectionClass $controllerReflection The reflection instance of the controller.
   * @return bool True if the given controller is the root controller, false otherwise.
   * @throws ReflectionException If there was an error processing a reflection.
   */
  private function isRootController(ReflectionClass $controllerReflection): bool
  {
    return $controllerReflection->getName() === $this->controllerManager->getRootControllerClass();
  }

  /**
   * Binds the request handler attributes to the given parameter.
   *
   * @param ReflectionParameter $param
   * @param mixed $dependency
   * @param Request $request
   * @return mixed
   * @throws EntryNotFoundException
   * @throws HttpException
   * @throws ReflectionException
   */
  private function bindRequestHandlerAttributes(ReflectionParameter $param, mixed $dependency, Request $request): mixed
  {
    $paramAttributes = $param->getAttributes();
    $paramTypeName = $param->getType() instanceof ReflectionUnionType
      ? $param->getType()->getTypes()[0]->getName()
      : $param->getType()?->getName();

    foreach ($paramAttributes as $attribute) {
      $paramAttributeArgs = $attribute->getArguments();
      $paramAttributeInstance = $attribute->newInstance();

      switch ($paramAttributeInstance::class) {
        case Param::class:
          if (empty($paramAttributeArgs)) {
            return ($paramTypeName === 'string')
              ? json_encode($request->getParams())
              : (object)$request->getParams();
          }
          return $request->getParams()[$paramAttributeInstance->key] ??
            $request->getParams()[$param->getPosition()] ??
            ($param->isOptional() ? $param->getDefaultValue() : null);

        case Query::class:
          if (empty($paramAttributeArgs)) {
            return ($paramTypeName === 'string')
              ? json_encode($request->getQuery())
              : $request->getQuery();
          }

          return $request->getQuery()->get($paramAttributeInstance->key) ??
            ($param->isOptional() ? $param->getDefaultValue() : null);

        case Body::class:
          $output = new ConsoleOutput();
          $output->setDecorated(true);

          if (empty($paramAttributeArgs)) {
            $body = ($paramTypeName === 'string')
              ? json_encode($request->getBody())
              : $request->getBody();
          } else {
            $key = $param->getName();
            $body = $paramAttributeInstance->value ?? null;

            if ($paramAttributeInstance->pipes) {
              if (is_array($paramAttributeInstance->pipes)) {
                foreach ($paramAttributeInstance->pipes as $pipe) {
                  $body = $this->transformBody($pipe, $body);
                }
              } else {
                $body = $this->transformBody($paramAttributeInstance->pipes, $body);
              }
            }
          }

          return is_object($body) ? TypeManager::castObjectToUserType($body, $paramTypeName) : $body;

        case Req::class:
          return $request;

        case Res::class:
          return Response::getInstance();

        default:
          if (property_exists($paramAttributeInstance, 'value')) {
            return $paramAttributeInstance->value;
          }
//        case Request::class:
//          $dependency = $request;
//          break;
//        case Response::class:
//          $dependency = Response::getInstance();
//          break;
//        case Body::class:
//          $body = Request::getInstance()->getBody();
//          if ($body->value) {
//            $dependency = $body->value;
//          } else {
//            foreach ($body as $key => $value) {
//              if (property_exists($dependency, $key)) {
//                $dependency->$key = $value;
//              }
//            }
//          }
//          break;
      }
    }

    return $dependency;
  }

  /**
   * Falls back to extracted route params for plain scalar handler arguments.
   *
   * @param ReflectionParameter $param
   * @param mixed $dependency
   * @param Request $request
   * @return mixed
   * @throws ReflectionException
   */
  private function resolveRouteParameterFallback(ReflectionParameter $param, mixed $dependency, Request $request): mixed
  {
    if (!is_null($dependency)) {
      return $dependency;
    }

    $paramType = $param->getType();
    $canBindScalarRouteValue = match (true) {
      is_null($paramType) => true,
      $paramType instanceof ReflectionUnionType =>
        !empty(array_filter($paramType->getTypes(), static fn($type) => $type->isBuiltin() && $type->getName() !== 'null')),
      default => $paramType->isBuiltin(),
    };

    if (!$canBindScalarRouteValue) {
      return $dependency;
    }

    $requestParams = $request->getParams();
    $routeValue = $requestParams[$param->getName()] ?? $requestParams[$param->getPosition()] ?? null;

    if (is_null($routeValue)) {
      return $param->isOptional() ? $param->getDefaultValue() : null;
    }

    return $this->castRouteParameterValue($param, $routeValue);
  }

  /**
   * Casts an extracted route parameter to the handler parameter's declared scalar type.
   *
   * @param ReflectionParameter $param
   * @param mixed $value
   * @return mixed
   */
  private function castRouteParameterValue(ReflectionParameter $param, mixed $value): mixed
  {
    $paramType = $param->getType();

    if (is_null($paramType)) {
      return $value;
    }

    $typeName = match (true) {
      $paramType instanceof ReflectionUnionType => $this->getPreferredBuiltInTypeName($paramType),
      default => $paramType->getName(),
    };

    return match ($typeName) {
      'int' => (int)$value,
      'float' => (float)$value,
      'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$value,
      'string' => (string)$value,
      'array' => is_array($value) ? $value : [$value],
      default => $value,
    };
  }

  /**
   * Returns the first non-null builtin type name from a union declaration.
   *
   * @param ReflectionUnionType $unionType
   * @return string
   */
  private function getPreferredBuiltInTypeName(ReflectionUnionType $unionType): string
  {
    foreach ($unionType->getTypes() as $type) {
      if ($type->isBuiltin() && $type->getName() !== 'null') {
        return $type->getName();
      }
    }

    return $unionType->getTypes()[0]->getName();
  }

  /**
   * Validates that constrained route params are compatible with the selected handler's parameter declarations.
   *
   * @param ReflectionMethod $handler
   * @param array<string, string> $constraints
   * @return void
   * @throws HttpException
   */
  private function validateMatchedRouteConstraints(ReflectionMethod $handler, array $constraints): void
  {
    if (empty($constraints)) {
      return;
    }

    foreach ($handler->getParameters() as $parameter) {
      $routeParamName = $this->getHandlerRouteParameterName($parameter, $constraints);

      if (is_null($routeParamName)) {
        continue;
      }

      $constraint = $constraints[$routeParamName];

      if ($this->isConstraintTypeCompatible($constraint, $parameter)) {
        continue;
      }

      $parameterType = $parameter->getType();
      $typeName = match (true) {
        $parameterType instanceof ReflectionUnionType =>
          implode('|', array_map(static fn($type) => $type->getName(), $parameterType->getTypes())),
        is_null($parameterType) => 'mixed',
        default => $parameterType->getName(),
      };

      throw new HttpException(
        "Route constraint '$constraint' for parameter '$routeParamName' on {$handler->class}::{$handler->getName()} " .
        "conflicts with declared PHP type '$typeName'."
      );
    }
  }

  /**
   * Resolves the route parameter name associated with a handler parameter.
   *
   * @param ReflectionParameter $parameter
   * @param array<string, string> $constraints
   * @return string|null
   * @throws ReflectionException
   */
  private function getHandlerRouteParameterName(ReflectionParameter $parameter, array $constraints): ?string
  {
    foreach ($parameter->getAttributes(Param::class) as $attribute) {
      $attributeArgs = $attribute->getArguments();
      $key = $attributeArgs['key'] ?? $attributeArgs[0] ?? null;

      if ($key && array_key_exists($key, $constraints)) {
        return $key;
      }
    }

    return array_key_exists($parameter->getName(), $constraints) ? $parameter->getName() : null;
  }

  /**
   * Determines whether a constrained route parameter is compatible with the handler's PHP parameter type.
   *
   * @param string $constraint
   * @param ReflectionParameter $parameter
   * @return bool
   */
  private function isConstraintTypeCompatible(string $constraint, ReflectionParameter $parameter): bool
  {
    $parameterType = $parameter->getType();

    if (is_null($parameterType)) {
      return true;
    }

    $expectedTypeNames = match ($constraint) {
      'int' => ['int'],
      'slug', 'uuid', 'alpha', 'alnum', 'hex', 'ulid' => ['string'],
      default => ['string'],
    };

    if ($parameterType instanceof ReflectionUnionType) {
      foreach ($parameterType->getTypes() as $type) {
        if ($type->getName() === 'mixed' || in_array($type->getName(), $expectedTypeNames, true)) {
          return true;
        }
      }

      return false;
    }

    return $parameterType->getName() === 'mixed' || in_array($parameterType->getName(), $expectedTypeNames, true);
  }

  /**
   * Transforms the given body using the given pipe.
   *
   * @param string|IPipeTransform $pipe The pipe to be used for transformation.
   * @param mixed $body The body to be transformed.
   * @param array|stdClass|null $metaData The body to be transformed.
   * @return mixed The transformed body.
   * @throws ContainerException
   * @throws ReflectionException
   */
  private function transformBody(string|IPipeTransform $pipe, mixed $body, array|stdClass|null $metaData = null): mixed
  {
    if (is_string($pipe)) {
      $pipe = $this->injector->resolve($pipe);
    }

    return $pipe->transform($body, $metaData);
  }
}
