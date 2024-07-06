<?php

namespace Assegai\Core\Http;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\IHttpArgumentsHost;
use Assegai\Core\Session;

/**
 * Class HttpArgumentsHost. Represents the HTTP arguments host.
 *
 * @package Assegai\Core\Http
 */
class HttpArgumentsHost implements IHttpArgumentsHost
{
  /**
   * The instance of the HTTP arguments host.
   *
   * @var HttpArgumentsHost|null $instance
   */
  protected static ?HttpArgumentsHost $instance = null;

  /**
   * HttpArgumentsHost constructor.
   */
  private final function __construct()
  {
  }

  /**
   * @return HttpArgumentsHost
   */
  public static function getInstance(): HttpArgumentsHost
  {
    if (!self::$instance)
    {
      self::$instance = new HttpArgumentsHost();
    }

    return self::$instance;
  }

  /**
   * Returns the HTTP request.
   *
   * @return Request The HTTP request.
   */
  public function getRequest(): Request
  {
    return Request::getInstance();
  }

  /**
   * Returns the response.
   *
   * @return Response The response.
   */
  public function getResponse(): Response
  {
    return Response::getInstance();
  }

  /**
   * The next handler in the request pipeline.
   *
   * @return mixed
   */
  public function getNext(): mixed
  {
    return null;
  }

  /**
   * The arguments passed to the handler. The arguments are the request, response, and next.
   *
   * @return array{0: Request, 1: Response, 2: mixed} An array of arguments passed to the handler.
   */
  public function getArgs(): array
  {
    return [$this->getRequest(), $this->getResponse(), $this->getNext()];
  }

  /**
   * @inheritDoc
   */
  public function getSession(): Session
  {
    return Session::getInstance();
  }
}