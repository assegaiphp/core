<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Attribute;

/**
 * Attribute to specify exception filters for a controller or method.
 *
 * @package Assegai\Core\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS|Attribute::TARGET_METHOD)]
readonly class UseFilters
{
  /**
   * The exception filter class name or an array of exception filter class names to use.
   *
   * @param class-string|array<class-string>|ExceptionFilterInterface[]|ExceptionFilterInterface $filters
   */
  public function __construct(
    public string|array|ExceptionFilterInterface $filters,
  )
  {
  }
}