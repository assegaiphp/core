<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

/**
 * Binds the current session variables that are available to the currently
 * executing script.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Session
{
  /**
   * @var object $value The session variables
   */
  public readonly object $value;

  /**
   * The constructor for the Session attribute.
   */
  public function __construct()
  {
    $this->value = (object)$_SESSION;
  }
}