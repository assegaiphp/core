<?php

namespace Assegai\Core\Http\Responses;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Rendering\View;
use Assegai\Core\Rendering\ViewEngine;
use Assegai\Orm\Queries\QueryBuilder\Results\DeleteResult;
use Assegai\Orm\Queries\QueryBuilder\Results\InsertResult;
use Assegai\Orm\Queries\QueryBuilder\Results\UpdateResult;

/**
 * Contains useful methods for managing the Response object.
 */
class Responder
{
  /**
   * @var Responder|null
   */
  private static ?Responder $instance = null;
  /**
   * @var ViewEngine
   */
  private ViewEngine $viewEngine;

  /**
   *
   */
  private final function __construct()
  {
    $this->viewEngine = ViewEngine::getInstance();
  }

  /**
   * @return Responder
   */
  public static function getInstance(): Responder
  {
    if (!self::$instance) {
      self::$instance = new Responder();
    }

    return self::$instance;
  }

  /**
   * @return Request
   */
  public function getRequest(): Request
  {
    return Request::getInstance();
  }

  /**
   * @param mixed $response
   * @param HttpStatusCode|int|null $code
   * @return never
   */
  public function respond(mixed $response, HttpStatusCode|int|null $code = null): never
  {
    if ($code)
    {
      $this->setResponseCode($code);
    }
    else if ($response instanceof Response)
    {
      $this->setResponseCode($response->getStatus());
    }

    if ($response?->getBody() instanceof View)
    {
      $this->viewEngine->render($response->getBody());
    }

    $responseString = match(true) {
      is_countable($response) =>  (is_array($response) && isset($response[0]) && is_scalar($response[0]) ?  : new ApiResponse(data: $response) ),
      ($response instanceof Response) => match($response->getContentType()) {
        ContentType::JSON => match (true) {
          ($response->getBody() instanceof DeleteResult) => strval($response->getBody()->affected),
          ($response->getBody() instanceof InsertResult),
          ($response->getBody() instanceof UpdateResult) => json_encode($response->getBody()->identifiers),
          default => new ApiResponse(data: $response->getBody()),
        },
        default => $response->getBody()
      },
      is_object($response) => json_encode($response),
      default => $response
    };

    exit($responseString);
  }

  /**
   * @param HttpStatusCode|int|null $code
   * @return void
   */
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