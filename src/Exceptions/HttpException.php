<?php

namespace Assegai\Core\Exceptions;

use Assegai\Core\Responses\HttpStatus;
use Assegai\Core\Responses\HttpStatusCode;
use Assegai\Core\Responses\Responder;
use Exception;
use stdClass;

class HttpException extends Exception
{
  protected readonly string|stdClass|array $response;

  public function __construct(string|stdClass|array $message = '', protected ?HttpStatusCode $status = null)
  {
    $this->response = $message;

    if (!$this->status)
    {
      $this->status = HttpStatus::InternalServerError();
    }

    Responder::getInstance()->setResponseCode($this->status);
    parent::__construct($this->getResponse());
  }

  public final function __toString(): string
  {
    return $this->getResponse();
  }

  public final function getResponse(): string
  {
    return json_encode((! is_string($this->response)) ? $this->response : [
      'statusCode' => $this->status->code,
      'message' => $this->response ?? $this->status->name
    ]);
  }

  public final function getStatus(): HttpStatusCode
  {
    return $this->status;
  }
}