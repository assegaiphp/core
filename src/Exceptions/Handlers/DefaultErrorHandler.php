<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\Concerns\EmitsErrorResponses;
use Assegai\Core\Exceptions\Handlers\Support\FrameworkErrorPageRenderer;
use Assegai\Core\Exceptions\Interfaces\ErrorHandlerInterface;
use Assegai\Core\Http\HttpStatus;
use Error;
use Throwable;

/**
 * The default error handler for the Assegai framework.
 *
 * @package Assegai\Core\Exceptions\Handlers
 */
class DefaultErrorHandler implements ErrorHandlerInterface
{
  use EmitsErrorResponses;

  /**
   * @inheritDoc
   */
  public function handle(int $errno, string $errstr, string $errfile, int $errline): void
  {
    $status = HttpStatus::fromInt(500);

    if ($this->shouldRenderHtmlErrorPage()) {
      $message = match (Config::environment()) {
        EnvironmentType::PRODUCTION => 'The framework hit an internal error while processing this request.',
        default => $errstr !== '' ? $errstr : 'A PHP runtime error occurred.',
      };

      $details = Config::environment() === EnvironmentType::PRODUCTION
        ? null
        : basename($errfile) . ':' . $errline;

      $this->emitErrorResponse(
        FrameworkErrorPageRenderer::render(
          statusCode: $status->code,
          statusName: $status->name,
          heading: 'Internal server error',
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
        'message' => $errstr,
        'error' => $status->name,
      ]
    };
    $this->emitErrorResponse(json_encode($response), ContentType::JSON, $status->code);
  }

  /**
   * @inheritDoc
   */
  public function handleError(Throwable $error): void
  {
    $this->handle($error->getCode(), $error->getMessage(), $error->getFile(), $error->getLine());
  }
}
