<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
readonly class Sse
{
  public function __construct(public string $path = '')
  {}
}
