<?php

namespace Assegai\Core\Exceptions\Handlers;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Exceptions\Handlers\Concerns\EmitsErrorResponses;
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
      $this->emitErrorResponse((string)$exception, ContentType::PLAIN, $exception->getStatus()->code);
    } else {
      $status = HttpStatus::fromInt(500);

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
