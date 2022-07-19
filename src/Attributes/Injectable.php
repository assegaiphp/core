<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\ScopeOptions;
use Attribute;
use JetBrains\PhpStorm\ArrayShape;

class_alias(ScopeOptions::class, 'InjectableOptions');

#[Attribute(Attribute::TARGET_CLASS)]
class Injectable
{
  public function __construct(
    #[ArrayShape(['scope' => 'Assegai\Core\Enumerations\Scope', 'durable' => 'bool'])]
    public readonly InjectableOptions|array|null $options = null,
  )
  {
  }
}