<?php

namespace Assegai\Core\Exceptions\Interfaces;

use Error;
use Throwable;

interface ErrorHandlerInterface
{
  /**
   * Handles an error.
   *
   * @param int $errno
   * @param string $errstr
   * @param string $errfile
   * @param int $errline
   */
  public function handle(int $errno, string $errstr, string $errfile, int $errline): void;

  /**
   * Handles an error.
   *
   * @param Throwable $error The error to handle.
   */
  public function handleError(Throwable $error): void;
}