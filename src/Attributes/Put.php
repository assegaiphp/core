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
  public function __construct(public readonly string $path = '')
  {
  }
}