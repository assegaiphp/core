<?php

namespace Assegai\Core\Exceptions\Http;

use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Responders\Responder;
use Exception;
use stdClass;

/**
 * Class HttpException
 */
class HttpException extends Exception
{
  protected readonly string|stdClass|array $response;
  protected ?HttpStatusCode $status;

  public function __construct(string|stdClass|array $message = '', ?HttpStatusCode $status = null)
  {
    $this->response = $message;

    $this->setStatus($status);
    parent::__construct($this->response);
  }

  public final function __toString(): string
  {
    return $this->getResponse();
  }

  public final function getResponse(): string
  {
    return json_encode((!is_string($this->response)) ? $this->response : ['statusCode' => $this->status->code, 'message' => $this->response ?? $this->status->name, 'error' => $this->status->name]);
  }

  public final function getStatus(): HttpStatusCode
  {
    return $this->status;
  }

  protected final function setStatus(?HttpStatusCode $status): void
  {
    $this->status = $status;

    if (!$this->status) {
      $this->status = HttpStatus::InternalServerError();
    }

    Responder::getInstance()->setResponseCode($this->status);
  }
}