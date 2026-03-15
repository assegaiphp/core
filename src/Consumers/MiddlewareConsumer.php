<?php

namespace Assegai\Core\Consumers;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\ControllerManager;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Interfaces\ConsumerInterface;
use Assegai\Core\Interfaces\MiddlewareInterface;
use Assegai\Core\Routing\Route;
use Assegai\Core\Util\Validator;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Class MiddlewareConsumer. This class is a consumer for middleware.
 *
 * @package Assegai\Core\Consumers
 */
class MiddlewareConsumer implements ConsumerInterface
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

  /**
   * @var array<int, MiddlewareInterface|callable|class-string<MiddlewareInterface>>
   */
  protected array $middleware = [];
  /**
   * @var array<int, array|string|object>
   */
  protected array $excludedRoutes = [];
  /**
   * @var array<int, array{exclude: array<int, array|string|object>, middleware: array<int, MiddlewareInterface|callable|class-string<MiddlewareInterface>>, routes: array<int, array|string|object>}>
   */
  protected array $routeMap = [];
  /**
   * @var array<class-string, array<int, array{method: RequestMethod, path: string}>>
   */
  protected array $controllerRoutesCache = [];
  protected ControllerManager $controllerManager;

  public function __construct()
  {
    $this->controllerManager = ControllerManager::getInstance();
  }

  /**
   * @inheritDoc
   */
  public function apply(array|string|object ...$class): self
  {
    foreach ($this->flattenTargets($class) as $target) {
      $this->middleware[] = $target;
    }

    return $this;
  }

  /**
   * @inheritDoc
   */
  public function exclude(array|string|object ...$routes): self
  {
    foreach ($this->flattenTargets($routes) as $route) {
      $this->excludedRoutes[] = $route;
    }

    return $this;
  }

  /**
   * @inheritDoc
   */
  public function forRoutes(array|string|object ...$routes): self
  {
    $targets = $this->flattenTargets($routes);

    if ($targets && $this->middleware) {
      $this->routeMap[] = [
        'exclude' => $this->excludedRoutes,
        'middleware' => $this->middleware,
        'routes' => $targets,
      ];
    }

    $this->middleware = [];
    $this->excludedRoutes = [];

    return $this;
  }

  /**
   * Merges another middleware consumer into the current instance.
   *
   * @param self $consumer
   * @return $this
   */
  public function merge(self $consumer): self
  {
    $this->routeMap = [...$this->routeMap, ...$consumer->routeMap];

    return $this;
  }

  /**
   * Returns the middleware that applies to the current request.
   *
   * @param Request $request
   * @return array<int, MiddlewareInterface|callable|class-string<MiddlewareInterface>>
   */
  public function getMiddlewareForRequest(Request $request): array
  {
    $middleware = [];

    foreach ($this->routeMap as $routeDefinition) {
      if (!$this->registrationMatchesRequest($routeDefinition, $request)) {
        continue;
      }

      $middleware = [...$middleware, ...$routeDefinition['middleware']];
    }

    return $middleware;
  }

  /**
   * Flattens variadic route definitions into a simple list.
   *
   * @param array<int, array|string|object> $targets
   * @return array<int, string|object>
   */
  private function flattenTargets(array $targets): array
  {
    $flattenedTargets = [];

    foreach ($targets as $target) {
      if (is_array($target)) {
        $flattenedTargets = [...$flattenedTargets, ...$this->flattenTargets($target)];
        continue;
      }

      $flattenedTargets[] = $target;
    }

    return $flattenedTargets;
  }

  /**
   * Determines if a middleware registration applies to the current request.
   *
   * @param array{exclude: array<int, array|string|object>, middleware: array<int, MiddlewareInterface|callable|class-string<MiddlewareInterface>>, routes: array<int, array|string|object>} $routeDefinition
   * @param Request $request
   * @return bool
   */
  private function registrationMatchesRequest(array $routeDefinition, Request $request): bool
  {
    $matchesIncludedRoute = false;

    foreach ($routeDefinition['routes'] as $route) {
      if ($this->targetMatchesRequest($route, $request)) {
        $matchesIncludedRoute = true;
        break;
      }
    }

    if (!$matchesIncludedRoute) {
      return false;
    }

    foreach ($routeDefinition['exclude'] as $excludedRoute) {
      if ($this->targetMatchesRequest($excludedRoute, $request)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Determines whether a configured route target matches the current request.
   *
   * @param array|string|object $target
   * @param Request $request
   * @return bool
   */
  private function targetMatchesRequest(array|string|object $target, Request $request): bool
  {
    if (is_array($target)) {
      foreach ($target as $nestedTarget) {
        if ($this->targetMatchesRequest($nestedTarget, $request)) {
          return true;
        }
      }

      return false;
    }

    if ($target instanceof Route) {
      return $this->routeMatchesRequest($target->path, $target->method, $request);
    }

    if (is_object($target)) {
      $target = $target::class;
    }

    if ($this->isControllerTarget($target)) {
      return $this->controllerMatchesRequest($target, $request);
    }

    return $this->routeMatchesRequest($target, null, $request);
  }

  /**
   * Determines if the given string target refers to a known controller class.
   *
   * @param string $target
   * @return bool
   */
  private function isControllerTarget(string $target): bool
  {
    if (!class_exists($target)) {
      return false;
    }

    if (!is_null($this->controllerManager->getResolvedControllerPath($target))) {
      return true;
    }

    try {
      $reflectionClass = new ReflectionClass($target);
    } catch (ReflectionException) {
      return false;
    }

    return !empty($reflectionClass->getAttributes(Controller::class)) ||
      !empty($reflectionClass->getAttributes(\Assegai\Attributes\Controller::class));
  }

  /**
   * Determines if any route belonging to the controller matches the current request.
   *
   * @param class-string $controllerClass
   * @param Request $request
   * @return bool
   */
  private function controllerMatchesRequest(string $controllerClass, Request $request): bool
  {
    foreach ($this->getControllerRoutes($controllerClass) as $route) {
      if ($this->routeMatchesRequest($route['path'], $route['method'], $request)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Returns the concrete controller routes that should inherit controller-bound middleware.
   *
   * @param class-string $controllerClass
   * @return array<int, array{method: RequestMethod, path: string}>
   */
  private function getControllerRoutes(string $controllerClass): array
  {
    if (isset($this->controllerRoutesCache[$controllerClass])) {
      return $this->controllerRoutesCache[$controllerClass];
    }

    try {
      $reflectionClass = new ReflectionClass($controllerClass);
    } catch (ReflectionException) {
      return [];
    }

    $controllerPath = $this->controllerManager->getResolvedControllerPath($controllerClass) ??
      $this->extractControllerPath($reflectionClass);
    $routes = [];

    foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
      foreach ($reflectionMethod->getAttributes() as $attribute) {
        if (!Validator::isValidRequestMapperAttribute($attribute)) {
          continue;
        }

        $attributeInstance = $attribute->newInstance();
        $routes[] = [
          'method' => $this->resolveRequestMethod($attribute->getName()),
          'path' => $this->combinePaths($controllerPath, $attributeInstance->path ?? '/'),
        ];

        break;
      }
    }

    return $this->controllerRoutesCache[$controllerClass] = $routes;
  }

  /**
   * Extracts the local path from the controller attribute.
   *
   * @param ReflectionClass $reflectionClass
   * @return string
   */
  private function extractControllerPath(ReflectionClass $reflectionClass): string
  {
    $attributes = $reflectionClass->getAttributes(Controller::class);

    if (!$attributes) {
      $attributes = $reflectionClass->getAttributes(\Assegai\Attributes\Controller::class);
    }

    if (!$attributes) {
      return '/';
    }

    /** @var Controller|\Assegai\Attributes\Controller $instance */
    $instance = $attributes[0]->newInstance();

    return $this->normalizePath($instance->path ?? '/');
  }

  /**
   * Resolves an HTTP mapper attribute name to its request method.
   *
   * @param class-string $attributeClass
   * @return RequestMethod
   */
  private function resolveRequestMethod(string $attributeClass): RequestMethod
  {
    return match ($attributeClass) {
      \Assegai\Core\Attributes\Http\Post::class => RequestMethod::POST,
      \Assegai\Core\Attributes\Http\Put::class => RequestMethod::PUT,
      \Assegai\Core\Attributes\Http\Patch::class => RequestMethod::PATCH,
      \Assegai\Core\Attributes\Http\Delete::class => RequestMethod::DELETE,
      \Assegai\Core\Attributes\Http\Options::class => RequestMethod::OPTIONS,
      \Assegai\Core\Attributes\Http\Head::class => RequestMethod::HEAD,
      default => RequestMethod::GET,
    };
  }

  /**
   * Determines if the given route target matches the request path and method.
   *
   * @param string $route
   * @param RequestMethod|null $method
   * @param Request $request
   * @return bool
   */
  private function routeMatchesRequest(string $route, ?RequestMethod $method, Request $request): bool
  {
    if ($method && $request->getMethod() !== $method) {
      return false;
    }

    return !is_null($this->matchRoutePath($route, $request->getPath()));
  }

  /**
   * Matches a route template against a request path and returns extracted params on success.
   *
   * @param string $route
   * @param string $path
   * @return array<int|string, string>|null
   * @throws HttpException
   */
  private function matchRoutePath(string $route, string $path): ?array
  {
    $routeSegments = $this->getPathSegments($route);
    $pathSegments = $this->getPathSegments($path);

    if (empty($routeSegments)) {
      return empty($pathSegments) ? [] : null;
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

    if (count($pathSegments) !== count($routeSegments)) {
      return null;
    }

    return $params;
  }

  /**
   * Parses a route segment and returns its matching metadata.
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
      'name' => $matches['name'] ?? null,
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
    if (!isset(self::ROUTE_CONSTRAINT_PATTERNS[$constraint])) {
      return false;
    }

    return preg_match(self::ROUTE_CONSTRAINT_PATTERNS[$constraint], $value) === 1;
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
   * Splits the given path into URI segments for route comparisons.
   *
   * @param string $path
   * @return string[]
   */
  private function getPathSegments(string $path): array
  {
    $normalizedPath = trim($this->normalizePath($path), '/');

    if ($normalizedPath === '') {
      return [];
    }

    return array_values(array_filter(explode('/', $normalizedPath), static fn(string $segment) => $segment !== ''));
  }
}
