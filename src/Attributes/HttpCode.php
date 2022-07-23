<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Response;
use Attribute;

#[Attribute]
class HttpCode
{
  /**
   * @param int|HttpStatusCode $code
   */
  public function __construct(public readonly int|HttpStatusCode $code)
  {
    $intCode = (is_int($this->code)) ? $code : $this->code->code;
    http_response_code($intCode);
  }
}