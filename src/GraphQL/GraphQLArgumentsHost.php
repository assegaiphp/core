<?php

namespace Assegai\Core\GraphQL;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Interfaces\IHttpArgumentsHost;
use Assegai\Core\Session;

class GraphQLArgumentsHost implements IHttpArgumentsHost
{
  /**
   * @return RequestInterface
   */
  public function getRequest(): RequestInterface
  {
    return Request::current();
  }

  /**
   * @return ResponseInterface
   */
  public function getResponse(): ResponseInterface
  {
    return Response::current();
  }

  /**
   * @return mixed
   */
  public function getNext(): mixed
  {
    // TODO: Implement getNext() method.
    return null;
  }

  /**
   * @return array
   */
  public function getArgs(): array
  {
    global $argv;
    return $argv;
  }

  public function getSession(): Session
  {
    return Session::getInstance();
  }
}
