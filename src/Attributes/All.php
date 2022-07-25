<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * Defines an endpoint that handles all the standard HTTP methods viz. `GET`, `POST`, `PUT`, `DELETE`,
 * `PATCH`, `OPTIONS` and `HEAD`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class All
{
  /**
   * @param string $path
   */
  public function __construct(public readonly string $path = '')
  {
    if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
      http_response_code(201);
    }
  }
}