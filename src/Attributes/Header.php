<?php

namespace Assegai\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION)]
class Header
{
  /**
   * @param string $key
   * @param string $value
   */
  public function __construct(
    public readonly string $key,
    public readonly string $value
  )
  {
    header("$this->key: $this->value");
  }
}