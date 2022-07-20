<?php

namespace Assegai\Core\Responses;

use Assegai\Core\Http\Request;

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

  public function respond(mixed $response, HttpStatusCode|int|null $code = 200): never
  {
    $this->setResponseCode($code);
    $responseString = match(true) {
      is_countable($response) => new ApiResponse(data: $response),
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