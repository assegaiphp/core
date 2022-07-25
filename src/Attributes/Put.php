<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * Route handler (method) Decorator. Routes HTTP PUT requests to the specified path.
 *
 * @see [Routing](https://docs.assegaiphp.com/controllers#routing)
 */
#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class Put
{
  /**
   * @param string $path
   */
  public function __construct(public readonly string $path = '')
  {
    http_response_code(200);
  }
}