<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * Binds a handler parameter to a captured host placeholder from the controller's host pattern.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class HostParam
{
  public function __construct(public ?string $key = null)
  {
  }
}
