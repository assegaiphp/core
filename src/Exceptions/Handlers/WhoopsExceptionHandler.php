<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Exceptions\Interfaces\ExceptionHandlerInterface;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class WhoopsExceptionHandler implements ExceptionHandlerInterface
{
  /**
   * The Whoops error handler.
   *
   * @var Run $whoops
   */
  protected Run $whoops;

  public function __construct()
  {
    $this->whoops = new Run();
    $this->whoops->pushHandler(new PrettyPageHandler());
    $this->whoops->register();
  }

  /**
   * @inheritDoc
   */
  public function handle(Throwable $exception): void
  {
    header('Content-Type: text/html');
    echo $this->whoops->handleException($exception);
  }
}