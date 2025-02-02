<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * An attribute that marks a class as an AssegaiPHP controller and provides metadata that determines how the controller
 * should be processed, instantiated and used at runtime.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Controller
{
  /**
   * @param string $path
   * @param string|string[]|null $host
   */
  public function __construct(
    public string            $path = '',
    public null|string|array $host = null,
  )
  {
  }
}