<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

/**
 * Binds the current request IP address to the target parameter
 *
 * @deprecated This attribute is deprecated and will be removed in future
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class Ip
{
  /**
   * @var string $value The IP address of the client
   */
  public string $value;

  /**
   * The constructor for the Ip attribute.
   */
  public function __construct()
  {
    $this->value = $_SERVER['REMOTE_ADDR'];
  }
}