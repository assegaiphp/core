<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

/**
 * Route handler method attribute for SSE GET requests
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
readonly class Sse
{
  public function __construct(public string $path = '')
  {}
}
