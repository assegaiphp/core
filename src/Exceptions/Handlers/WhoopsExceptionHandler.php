<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Interfaces\ExceptionHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
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
   * WhoopsExceptionHandler constructor.
   *
   * @inheritDoc
   */
  public function __construct(protected LoggerInterface $logger)
  {
  }

  /**
   * @inheritDoc
   */
  public function handle(Throwable $exception): void
  {
    $whoops = $this->createWhoopsRun();

    if (!headers_sent()) {
      header('Content-Type: ' . $this->getContentType());
    }

    if ($exception instanceof HttpException) {
      $whoops->sendHttpCode($exception->getCode());
    }

    $this->logger->error($exception->getMessage());
    echo $whoops->handleException($exception);
  }

  /**
   * Builds a fresh Whoops runner for the current request context.
   *
   * @return Run
   */
  protected function createWhoopsRun(): Run
  {
    $whoops = new Run();
    $whoops->pushHandler(match ($this->getResponseMode()) {
      'plain' => new PlainTextHandler(),
      'json' => new JsonResponseHandler(),
      default => new PrettyPageHandler(),
    });

    return $whoops;
  }

  /**
   * @return 'html'|'json'|'plain'
   */
  protected function getResponseMode(): string
  {
    if ($this->isCliContext()) {
      return 'plain';
    }

    return $this->isHtmlRequest() ? 'html' : 'json';
  }

  protected function getContentType(): string
  {
    return match ($this->getResponseMode()) {
      'plain' => 'text/plain',
      'json' => 'application/json',
      default => 'text/html',
    };
  }

  protected function isCliContext(): bool
  {
    return PHP_SAPI === 'cli';
  }

  protected function isHtmlRequest(): bool
  {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
  }
}
