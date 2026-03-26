<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\Concerns\EmitsErrorResponses;
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

      error_log($exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL . PHP_EOL, 0);
    }

    $this->logger->error($exception->getMessage());

    $isProduction = Config::environment() === EnvironmentType::PRODUCTION;

    $heading = match ($statusCode) {
      405 => 'Method not allowed',
      500 => 'Internal server error',
      default => 'Page not found',
    };

    $message = match ($statusCode) {
      405 => 'The request reached the right route, but this HTTP method is not allowed there.',
      500 => 'Something went wrong while processing this request. Try again in a moment.',
      default => $isProduction
        ? 'The requested page could not be found.'
        : ($message !== '' ? $message : 'The requested page could not be found.'),
    };

    $statusName = HttpStatus::fromInt($statusCode)->name;
    $details = $isProduction || $statusCode === 404
      ? null
      : basename($exception->getFile()) . ':' . $exception->getLine();
    $body = FrameworkErrorPageRenderer::render($statusCode, $statusName, $heading, $message, $details);

    $this->emitErrorResponse($body, ContentType::HTML, $statusCode);
  }
}
