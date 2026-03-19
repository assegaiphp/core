<?php

namespace Assegai\Core\Http;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Injector;
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
   * @return RequestInterface The HTTP request.
   */
  public function getRequest(): RequestInterface
  {
    return Injector::getInstance()->get(RequestInterface::class) ?? Request::current();
  }

  /**
   * Returns the response.
   *
   * @return ResponseInterface The response.
   */
  public function getResponse(): ResponseInterface
  {
    return Injector::getInstance()->get(ResponseInterface::class) ?? Response::current();
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
   * @return array{0: RequestInterface, 1: ResponseInterface, 2: mixed} An array of arguments passed to the handler.
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
    return Injector::getInstance()->get(Session::class) ?? Session::getInstance();
  }
}
