<?php

namespace Assegai\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION|Attribute::TARGET_METHOD)]
class Delete
{
  public function __construct(public readonly string $path = '')
  {
  }
}