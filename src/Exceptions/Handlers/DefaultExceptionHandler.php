<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
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
      echo $exception;
    } else {
      $status = HttpStatus::fromInt(500);
      http_response_code($status->code);

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
      echo json_encode($response);
    }
  }
}