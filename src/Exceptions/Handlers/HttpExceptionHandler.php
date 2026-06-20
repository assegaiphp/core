<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\Concerns\EmitsErrorResponses;
use Assegai\Core\Exceptions\Handlers\Concerns\LogsHandledExceptions;
use Assegai\Core\Exceptions\Handlers\Support\FrameworkErrorPageRenderer;
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
  use EmitsErrorResponses;
  use LogsHandledExceptions;

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
    $statusCode = 500;
    $message = 'Something went wrong while processing this request.';

    if ($exception instanceof HttpException) {
      $statusCode = $exception->getStatus()->code;
      $message = $exception->getMessage();
    }

    $this->logHandledException($exception);

    $isProduction = Config::environment() === EnvironmentType::PRODUCTION;

    $heading = match ($statusCode) {
      403 => 'Forbidden',
      404 => 'Page not found',
      405 => 'Method not allowed',
      500 => 'Internal server error',
      default => HttpStatus::fromInt($statusCode)->name,
    };

    $message = match ($statusCode) {
      403 => 'You do not have permission to access this page.',
      404 => $isProduction
        ? 'The requested page could not be found.'
        : ($message !== '' ? $message : 'The requested page could not be found.'),
      405 => 'The request reached the right route, but this HTTP method is not allowed there.',
      500 => 'Something went wrong while processing this request. Try again in a moment.',
      default => $message !== '' ? $message : 'An unexpected HTTP error occurred.',
    };

    $statusName = HttpStatus::fromInt($statusCode)->name;
    $details = $isProduction || $statusCode === 404
      ? null
      : basename($exception->getFile()) . ':' . $exception->getLine();
    $body = FrameworkErrorPageRenderer::render($statusCode, $statusName, $heading, $message, $details);

    $this->emitErrorResponse($body, ContentType::HTML, $statusCode);
  }
}
