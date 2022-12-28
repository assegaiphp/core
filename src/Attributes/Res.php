<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Http\Responses\Response;
use Attribute;

/**
 * Binds the current system `Response` object to the target parameter.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Res
{
  public readonly mixed $value;
  public function __construct()
  {
    $this->value = Response::getInstance();
  }
}