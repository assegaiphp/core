<?php

namespace Assegai\Core\Attributes;

use Attribute;
use Throwable;

/**
 * Attribute to specify exception handling for a controller or method.
 *
 * @package Assegai\Core\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS|Attribute::TARGET_METHOD)]
readonly class OnException
{
  /**
   * The exception class name or an array of exception class names to handle.
   * If a single exception class is provided, it will be used for handling.
   * If an array is provided, all exceptions in the array will be handled.
   *
   * @param string|array<class-string> $exception The exception class name or an array of exception class
   * names to handle.
   */
  public function __construct(
    public string|array $exception,
  )
  {
  }
}