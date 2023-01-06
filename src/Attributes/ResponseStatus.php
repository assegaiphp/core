<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Responder;
use Attribute;

/**
 * An attribute that defines the default HTTP response code of a handler.
 */
#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class ResponseStatus
{
  /**
   * @param HttpStatusCode|int $code
   * @param Responder|null $responder
   */
  public function __construct(
    public readonly HttpStatusCode|int $code,
    public readonly ?Responder $responder = null
  )
  {
    $activeResponder = $this->responder ?? Responder::getInstance();
    $activeResponder->setResponseCode($this->code);
  }
}