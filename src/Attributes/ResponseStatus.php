<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Http\HttpStatusCode;
use Attribute;

/**
 * An attribute that defines the default HTTP response code of a handler.
 */
#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class ResponseStatus
{
  /**
   * @param HttpStatusCode|int $code
   * @param string|null $responder
   */
  public function __construct(
    public readonly HttpStatusCode|int $code,
    public readonly ?string $responder = null
  ) {}
}
