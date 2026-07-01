<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\Concerns\EmitsErrorResponses;
use Assegai\Core\Exceptions\Handlers\Concerns\LogsHandledExceptions;
use Assegai\Core\Exceptions\Handlers\Support\FrameworkErrorPageRenderer;
use Assegai\Core\Exceptions\Interfaces\ErrorHandlerInterface;
use Assegai\Core\Http\HttpStatus;
use ErrorException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * The default error handler for the Assegai framework.
 *
 * @package Assegai\Core\Exceptions\Handlers
 */
class DefaultErrorHandler implements ErrorHandlerInterface
{
  use EmitsErrorResponses;
  use LogsHandledExceptions;

  protected LoggerInterface $logger;

  public function __construct(?LoggerInterface $logger = null)
  {
    $this->logger = $logger ?? new NullLogger();
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
    $this->logHandledException($error);
    $this->emitError($error->getMessage(), $error->getFile(), $error->getLine());
  }

  private function emitError(string $message, string $file, int $line): void
  {
    $status = HttpStatus::fromInt(500);

    if ($this->shouldRenderHtmlErrorPage()) {
      $bodyMessage = match (Config::environment()) {
        EnvironmentType::PRODUCTION => 'The framework hit an internal error while processing this request.',
        default => $message !== '' ? $message : 'A PHP runtime error occurred.',
      };

      $details = Config::environment() === EnvironmentType::PRODUCTION
        ? null
        : basename($file) . ':' . $line;

      $this->emitErrorResponse(
        FrameworkErrorPageRenderer::render(
          statusCode: $status->code,
          statusName: $status->name,
          heading: 'Internal server error',
          message: $bodyMessage,
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
        'message' => $message,
        'error' => $status->name,
      ]
    };
    $this->emitErrorResponse(json_encode($response) ?: '{}', ContentType::JSON, $status->code);
  }
}
