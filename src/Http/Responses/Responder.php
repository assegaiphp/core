<?php

namespace Assegai\Core\Http\Responses;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Requests\Request;

class Responder
{
  private static ?Responder $instance = null;

  private final function __construct()
  {
  }

  public static function getInstance(): Responder
  {
    if (!self::$instance) {
      self::$instance = new Responder();
    }

    return self::$instance;
  }

  public function getRequest(): Request
  {
    return Request::getInstance();
  }

  public function respond(mixed $response, HttpStatusCode|int|null $code = null): never
  {
    if ($code)
    {
      $this->setResponseCode($code);
    }

    $responseString = match(true) {
      is_countable($response) => new ApiResponse(data: $response),
      ($response instanceof Response) => match($response->getContentType()) {
        ContentType::JSON => new ApiResponse(data: $response->getBody()),
        default => $response->getBody()
      },
      is_object($response) => json_encode($response),
      default => $response
    };

    exit($responseString);
  }

  public function setResponseCode(HttpStatusCode|int|null $code = 200): void
  {
    $codeObject = $code;

    if (!$codeObject)
    {
      return;
    }

    if (is_int($code))
    {
      $codeObject = HttpStatus::fromInt($code);
    }

    http_response_code($codeObject->code);
  }
}