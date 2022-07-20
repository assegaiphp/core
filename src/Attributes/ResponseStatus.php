<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Responses\HttpStatusCode;
use Assegai\Core\Responses\Responder;
use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class ResponseStatus
{
  public function __construct(
    public readonly HttpStatusCode|int $code,
    public readonly ?Responder $responder = null
  )
  {
    $activeResponder = $this->responder ?? Responder::getInstance();
    $activeResponder->setResponseCode($this->code);
  }
}