<?php

namespace Assegai\Core\Consumers;

use Assegai\Core\Interfaces\ConsumerInterface;
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
   * @param array $middleware
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
   */
  public function apply(object|string $class): self
  {
    // TODO: Implement apply() method.
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
          // TODO: Handle Route consumers
        }
      }
    } else if (is_object($routes)) {
      // TODO: Handle object consumers
    } else {
      // TODO: Handle all other consumers
    }

    return $this;
  }
}