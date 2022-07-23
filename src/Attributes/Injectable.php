<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\ScopeOptions as InjectableOptions;
use Attribute;
use JetBrains\PhpStorm\ArrayShape;

#[Attribute(Attribute::TARGET_CLASS)]
class Injectable
{
  /**
   * @param InjectableOptions|array|null $options
   */
  public function __construct(
    #[ArrayShape(['scope' => 'Assegai\Core\Enumerations\Scope', 'durable' => 'bool'])]
    public readonly InjectableOptions|array|null $options = null,
  )
  {
  }
}