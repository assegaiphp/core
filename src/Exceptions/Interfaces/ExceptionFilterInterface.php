<?php

namespace Assegai\Core\Exceptions\Interfaces;

use Assegai\Core\ArgumentsHost;
use Throwable;

/**
 * Interface ExceptionFilterInterface
 * @package Assegai\Core\Exceptions\Interfaces
 *
 * This interface defines the contract for exception filters in the application.
 * Exception filters are responsible for handling exceptions thrown during the
 * execution of the application.
 */
interface ExceptionFilterInterface
{
  /**
   * The method that will be called when an exception is thrown.
   *
   * @param Throwable $throwable The exception that was thrown.
   * @param ArgumentsHost $host The context in which the exception was thrown.
   * @return void
   */
  public function catch(Throwable $throwable, ArgumentsHost $host): void;
}