<?php

namespace Assegai\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class Patch
{
  public function __construct(public readonly string $path = '')
  {
  }
}