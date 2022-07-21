<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * Route handler (method) Decorator. Routes HTTP HEAD requests to the specified path.
 *
 * @see [Routing](https://docs.assegaiphp.com/controllers#routing)
 */
#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION)]
class Head
{
  public function __construct(public readonly string $path = '')
  {
  }
}