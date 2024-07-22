<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Interfaces\ExceptionHandlerInterface;
use Assegai\Core\Http\HttpStatus;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class HttpExceptionHandler
 *
 * Handles HTTP exceptions.
 *
 * @package Assegai\Core\Exceptions\Handlers
 */
class HttpExceptionHandler implements ExceptionHandlerInterface
{
  /**
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
    $statusCode = http_response_code();
    if ($exception instanceof HttpException) {
      $statusCode = $exception->getStatus()->code;
      if (!headers_sent()) {
        header('Content-Type: text/html');
      }
      error_log($exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL . PHP_EOL, 0);

      http_response_code($statusCode);
    }

    $this->logger->error($exception->getMessage());

    $content = match ($statusCode) {
      405 => <<<CONTENT
  <h1>Error 405 - Method Not Allowed</h1>
  <p>Sorry, the method you are trying to use is not allowed on this server.</p>
CONTENT,
      500 => <<<CONTENT
  <h1>500 - Internal Server Error</h1>
  <p>Sorry, something went wrong. Please try again later.</p>
CONTENT,
      default => <<<CONTENT
  <h1>404 - Page Not Found</h1>
  <p>{$exception->getMessage()}</p>
CONTENT,
    };

    $statusName = HttpStatus::fromInt($statusCode)->name;
    echo <<<HTML
  <head>
      <title>Error $statusCode - $statusName</title>
  </head>
  <style>
      body {
          background-color: #110e1e;
          color: white;
          font-family: Arial, sans-serif;
          text-align: center;
          padding: 50px;
      }
      h1 {
          font-size: 3em;
          color: #92509f;
      }
      img {
          width: 200px;
      }
  </style>
  <img src="/images/logo.png" alt="Logo" width="200">
  $content
HTML;
  }
}