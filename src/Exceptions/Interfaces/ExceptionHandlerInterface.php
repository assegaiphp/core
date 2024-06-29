<?php

namespace Assegai\Core\Exceptions\Interfaces;

use Throwable;

/**
 * Interface ExceptionHandlerInterface
 *
 * @package Assegai\Core\Exceptions\Interfaces
 */
interface ExceptionHandlerInterface
{
  /**
   * Handles an exception.
   *
   * @param Throwable $exception
   */
  public function handle(Throwable $exception): void;
}