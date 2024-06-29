<?php

namespace Assegai\Core\Exceptions\Interfaces;

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
}