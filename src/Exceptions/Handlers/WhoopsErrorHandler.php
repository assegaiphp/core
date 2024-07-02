<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Exceptions\Interfaces\ErrorHandlerInterface;
use ErrorException;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * The Whoops error handler for the Assegai framework.
 *
 * @package Assegai\Core\Exceptions\Handlers
 */
class WhoopsErrorHandler implements ErrorHandlerInterface
{
  /**
   * The Whoops error handler.
   *
   * @var Run $whoops
   */
  protected Run $whoops;

  /**
   * WhoopsExceptionHandler constructor.
   */
  public function __construct()
  {
    try {
      $this->whoops = new Run();
      $this->whoops->pushHandler(new PrettyPageHandler());
      $this->whoops->register();
    } catch (Throwable $throwable) {
      if (! headers_sent() ) {
        header('Content-Type: text/html');
      }
      echo $throwable->getMessage();
      exit(1);
    }
  }

  /**
   * @inheritDoc
   */
  public function handle(int $errno, string $errstr, string $errfile, int $errline): void
  {
    $this->handleError(new ErrorException($errstr, 0, $errno, $errfile, $errline));
  }

  /**
   * @inheritDoc
   */
  public function handleError(Throwable $error): void
  {
    if (! headers_sent() ) {
      header('Content-Type: text/html');
    }
    echo $this->whoops->handleException($error);
  }
}