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
 * The default exception handler for the Assegai framework.
 *
 * @package Assegai\Core\Exceptions\Handlers
 */
class DefaultExceptionHandler implements ExceptionHandlerInterface
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
    if ($exception instanceof HttpException) {
      $statusCode = $exception->getStatus()->code;

      if ($this->shouldRenderHtmlErrorPage()) {
        $this->emitErrorResponse(
          FrameworkErrorPageRenderer::render(
            statusCode: $statusCode,
            statusName: $this->statusName($statusCode),
            heading: $this->statusName($statusCode),
            message: (string) $exception,
          ),
          ContentType::HTML,
          $statusCode,
        );
        return;
      }

      $this->emitErrorResponse((string)$exception, ContentType::PLAIN, $statusCode);
    } else {
      $status = HttpStatus::fromInt(500);

      if ($this->shouldRenderHtmlErrorPage()) {
        $message = match (Config::environment()) {
          EnvironmentType::PRODUCTION => 'Something went wrong while processing this request.',
          default => $exception->getMessage() !== '' ? $exception->getMessage() : 'An unexpected exception was raised.',
        };

        $details = Config::environment() === EnvironmentType::PRODUCTION
          ? null
          : basename($exception->getFile()) . ':' . $exception->getLine();

        $this->emitErrorResponse(
          FrameworkErrorPageRenderer::render(
            statusCode: $status->code,
            statusName: $status->name,
            heading: 'Unhandled exception',
            message: $message,
            details: $details,
          ),
          ContentType::HTML,
          $status->code,
        );
        return;
      }

      $response = match (Config::environment()) {
        EnvironmentType::PRODUCTION => [
          'statusCode' => $status->code,
          'message' => $status->name,
        ],
        default => [
          'statusCode' => $status->code,
          'message' =>  $exception->getMessage(),
          'error' => $status->name,
        ]
      };
      $this->emitErrorResponse(json_encode($response), ContentType::JSON, $status->code);
    }
  }
}
