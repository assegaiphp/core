<?php

namespace Assegai\Core\Routing;

use Assegai\Core\Enumerations\Http\RequestMethod;

/**
 * Class Route
 *
 * This class represents a Route in the AssegaiPHP framework.
 *
 * A route is defined by a path and a request method (GET, POST, etc.).
 */
readonly class Route
{
  /**
   * Route constructor.
   *
   * @param string $path The path of the route.
   * @param RequestMethod $method The request method for the route. Defaults to GET.
   */
  public function __construct(
    public string        $path,
    public RequestMethod $method = RequestMethod::GET
  )
  {
  }
}