<?php

namespace Assegai\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class All
{
  public function __construct(public readonly string $path = '')
  {
  }
}