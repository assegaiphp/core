<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Interfaces\ICanActivate;
use Attribute;

/**
 * Specifies which Guards determine whether an endpoint can be activated.
 */
#[Attribute]
class UseGuards
{
  /**
   * @param ICanActivate[]|ICanActivate $guard
   */
  public function __construct(public readonly array|ICanActivate $guard)
  {}
}