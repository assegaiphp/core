<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

/**
 * Route handler (method) Decorator. Routes HTTP PATCH requests to the specified path.
 *
 * @see [Routing](https://docs.assegaiphp.com/controllers#routing)
 */

#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class Patch
{
  /**
   * @param string $path
   */
  public function __construct(public readonly string $path = '')
  {
    http_response_code(200);
  }
}