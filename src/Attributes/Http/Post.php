<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

/**
 * Route handler (method) Decorator. Routes HTTP POST requests to the specified path.
 *
 * @see [Routing](https://docs.assegaiphp.com/controllers#routing)
 */

#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION)]
class Post
{
  /**
   * @param string $path
   */
  public function __construct(
    public readonly string $path = ''
  )
  {
    http_response_code(201);
  }
}