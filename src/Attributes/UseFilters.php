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
   * Class-string filters are resolved through dependency injection. Configured
   * filter instances can be supplied when constructor options are route-specific.
   *
   * @param class-string<ExceptionFilterInterface>|array<class-string<ExceptionFilterInterface>|ExceptionFilterInterface>|ExceptionFilterInterface $filters
   */
  public function __construct(
    public string|array|ExceptionFilterInterface $filters,
  )
  {
  }
}
