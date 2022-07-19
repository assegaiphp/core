<?php

namespace Assegai\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Module
{
  public function __construct(
    public readonly array $providers = [],
    public readonly array $controllers = [],
    public readonly array $imports = [],
    public readonly array $exports = [],
  )
  {
  }
}