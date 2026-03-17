<?php

namespace Assegai\Core\Attributes\Http;

use Assegai\Core\Http\HttpStatusCode;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION)]
class Redirect
{
  /**
   * @param string $url
   * @param HttpStatusCode|int $status
   */
  public function __construct(
    public readonly string $url,
    public readonly HttpStatusCode|int $status = 302,
  ) {}
}
