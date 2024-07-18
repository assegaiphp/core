<?php

namespace Assegai\Core\Exceptions\Interfaces;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Interface ExceptionHandlerInterface
 *
 * @package Assegai\Core\Exceptions\Interfaces
 */
interface ExceptionHandlerInterface
{
  /**
   * ExceptionHandlerInterface constructor.
   *
   * @param LoggerInterface $logger The logger.
   */
  public function __construct(LoggerInterface $logger);

  /**
   * Handles an exception.
   *
   * @param Throwable $exception
   */
  public function handle(Throwable $exception): void;
}