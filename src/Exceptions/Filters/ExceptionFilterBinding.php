<?php

namespace Assegai\Core\Exceptions\Filters;

use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;

/**
 * Associates an exception filter definition with the throwable types it handles.
 */
final readonly class ExceptionFilterBinding
{
  /**
   * @param class-string<ExceptionFilterInterface>|ExceptionFilterInterface $filter
   * @param array<int, class-string<\Throwable>> $exceptionTypes
   */
  public function __construct(
    public string|ExceptionFilterInterface $filter,
    public array $exceptionTypes = [],
  )
  {
  }
}
