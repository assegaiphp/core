<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * Route handler (method) Decorator. Routes HTTP DELETE requests to the specified path.
 *
 * @see [Routing](https://docs.assegaiphp.com/controllers#routing)
 */
#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class Delete
{
  public function __construct(public readonly string $path = '')
  {
  }
}