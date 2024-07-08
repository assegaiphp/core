<?php

namespace Assegai\Core\Consumers;

use Assegai\Core\Interfaces\ConsumerInterface;
use Assegai\Core\Interfaces\MiddlewareInterface;
use Assegai\Core\Routing\Route;

/**
 * Class MiddlewareConsumer. This class is a consumer for middleware.
 *
 * @package Assegai\Core\Consumers
 */
class MiddlewareConsumerInterface implements ConsumerInterface
{
  /**
   * MiddlewareConsumer constructor.
   *
   * @param array<MiddlewareInterface> $middleware
   */
  protected array $middleware = [];
  /**
   * @var array $routeMap
   */
  protected array $routeMap = [];

  /**
   * MiddlewareConsumer constructor.
   *
   * @inheritDoc
   * @param MiddlewareInterface|class-string<MiddlewareInterface> $class
   */
  public function apply(object|string $class): self
  {
    if ($class instanceof MiddlewareInterface && ! in_array($class, $this->middleware)) {
      $this->middleware[] = $class;
    }
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function forRoutes(array|string|object $routes): self
  {
    if (is_array($routes)) {
      foreach ($routes as $route) {
        if ($route instanceof Route) {
          foreach ($this->middleware as $middleware) {
            if (! is_array($this->routeMap[$route->path]) ) {
              $this->routeMap[$route->path] = [];
            }
            $this->routeMap[$route->path][] = $middleware;
          }
        }
      }
    } else if (is_object($routes)) {
      // TODO: Handle object consumers
    } else {
      // TODO: Handle all other consumers
    }
    $this->middleware = [];

    return $this;
  }
}