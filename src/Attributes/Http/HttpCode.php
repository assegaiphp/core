<?php

namespace Assegai\Core\Attributes\Http;

use Assegai\Core\Http\HttpStatusCode;
use Attribute;

#[Attribute]
class HttpCode
{
  /**
   * @param int|HttpStatusCode $code
   */
  public function __construct(public readonly int|HttpStatusCode $code)
  {}
}
