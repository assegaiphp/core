<?php

namespace Assegai\Core\Exceptions\Handlers\Concerns;

use Assegai\Core\Exceptions\Http\HttpException;
use Throwable;

trait LogsHandledExceptions
{
  protected function logHandledException(Throwable $exception): void
  {
    if ($exception instanceof HttpException && $exception->getStatus()->code < 500) {
      $this->logger->debug($exception->getMessage());
      return;
    }

    $this->logger->error($exception->getMessage());

    if ($exception instanceof HttpException) {
      error_log($exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL . PHP_EOL, 0);
    }
  }
}
