<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Exceptions\Interfaces\ErrorHandlerInterface;
use Assegai\Core\Http\HttpStatus;
use Error;

/**
 * The default error handler for the Assegai framework.
 *
 * @package Assegai\Core\Exceptions\Handlers
 */
class DefaultErrorHandler implements ErrorHandlerInterface
{
  /**
   * @inheritDoc
   */
  public function handle(int $errno, string $errstr, string $errfile, int $errline): void
  {
    $status = HttpStatus::fromInt(500);
    http_response_code($status->code);

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
    echo json_encode($response);
  }

  /**
   * @inheritDoc
   */
  public function handleError(Error $error): void
  {
    $this->handle($error->getCode(), $error->getMessage(), $error->getFile(), $error->getLine());
  }
}