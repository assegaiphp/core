<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

/**
 * Defines an endpoint that handles all the standard HTTP methods viz. `GET`, `POST`, `PUT`, `DELETE`,
 * `PATCH`, `OPTIONS` and `HEAD`.
 */
#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION)]
class All
{
  /**
   * @param string $path
   */
  public function __construct(public readonly string $path = '')
  {}
}
