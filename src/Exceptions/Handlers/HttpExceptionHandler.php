<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Interfaces\ExceptionHandlerInterface;
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
  public function handle(Throwable $exception): void
  {
    if ($exception instanceof HttpException) {
      header('Content-Type: text/html');
      http_response_code($exception->getCode());
      $content = match ($exception->getCode()) {
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
    <p>Sorry, the page you are looking for might have been removed or is temporarily unavailable.</p>
CONTENT,
      };

      echo <<<HTML
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
}