<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Interfaces\IActivateable;
use Attribute;

#[Attribute]
class UseGuards
{
  /**
   * @param IActivateable[]|IActivateable $guard
   */
  public function __construct(public readonly array|IActivateable $guard)
  {}
}