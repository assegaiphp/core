<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\ScopeOptions;
use Attribute;

class_alias(ScopeOptions::class, 'InjectableOptions');

#[Attribute(Attribute::TARGET_CLASS)]
class Injectable
{
  public function __construct(
    public readonly ?InjectableOptions $options = null,
  )
  {
  }
}