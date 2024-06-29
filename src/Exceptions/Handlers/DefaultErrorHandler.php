<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Exceptions\Interfaces\ErrorHandlerInterface;
use Assegai\Core\Http\HttpStatus;

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
}