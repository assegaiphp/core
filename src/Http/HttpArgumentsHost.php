<?php

namespace Assegai\Core\Http;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\IHttpArgumentsHost;

class HttpArgumentsHost implements IHttpArgumentsHost
{
  protected static ?HttpArgumentsHost $instance = null;

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
   * @return Request
   */
  public function getRequest(): Request
  {
    return Request::getInstance();
  }

  /**
   * @return Response
   */
  public function getResponse(): Response
  {
    return Response::getInstance();
  }

  /**
   * @return mixed
   */
  public function getNext(): mixed
  {
    return null;
  }

  /**
   * @return array
   */
  public function getArgs(): array
  {
    return [$this->getRequest(), $this->getResponse(), $this->getNext()];
  }
}