<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Interfaces\ExceptionHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * The Whoops error handler for the Assegai framework.
 *
 * @package Assegai\Core\Exceptions\Handlers
 */
class WhoopsExceptionHandler implements ExceptionHandlerInterface
{
  /**
   * The Whoops error handler.
   *
   * @var Run $whoops
   */
  protected Run $whoops;

  /**
   * WhoopsExceptionHandler constructor.
   *
   * @inheritDoc
   */
  public function __construct(protected LoggerInterface $logger)
  {
    try {
      $this->whoops = new Run();
      $this->whoops->pushHandler(new PrettyPageHandler());
      $this->whoops->register();
    } catch (Throwable $throwable) {
      if (! headers_sent() ) {
        header('Content-Type: text/html');
      }
      $this->logger->error($throwable->getMessage());
      echo $throwable->getMessage();
      exit(1);
    }
  }

  /**
   * @inheritDoc
   */
  public function handle(Throwable $exception): void
  {
    if (! headers_sent() ) {
      header('Content-Type: text/html');
    }
    if ($exception instanceof HttpException) {
      $this->whoops->sendHttpCode($exception->getCode());
    }
    $this->logger->error($exception->getMessage());
    echo $this->whoops->handleException($exception);
  }
}