<?php

namespace Assegai\Core\Consumers;

use Assegai\Core\Interfaces\IConsumer;
use Assegai\Core\Routing\Route;

class MiddlewareConsumer implements IConsumer
{
  protected array $middleware = [];
  protected array $routeMap = [];

  public function apply(object|string $class): self
  {

    return $this;
  }

  /**
   * @param string[]|object[]|string|object $routes
   * @return $this
   */
  public function forRoutes(array|string|object $routes): self
  {
    if (is_array($routes))
    {
      foreach ($routes as $route)
      {
        if ($route instanceof Route)
        {
          // TODO: Handle Route consumers
        }
      }
    }
    else if (is_object($routes))
    {
      // TODO: Handle object consumers
    }
    else
    {
      // TODO: Handle all other consumers
    }

    return $this;
  }
}