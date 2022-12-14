<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

/**
 * The HTTP GET method requests a representation of the specified resource.
 * Requests using GET should only be used to request data (they shouldn't
 * include data).
 *
 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods/GET
 */
#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION)]
class Get
{
  /**
   * @param string $path
   */
  public function __construct( public readonly string $path = '')
  {
    http_response_code(200);
  }
}