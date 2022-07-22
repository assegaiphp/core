<?php

namespace Assegai\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
  /**
   * @param string $path
   * @param string|string[]|null $host
   */
  public function __construct(
    public readonly string $path = '',
    public readonly null|string|array $host = null,
  )
  {
  }
}