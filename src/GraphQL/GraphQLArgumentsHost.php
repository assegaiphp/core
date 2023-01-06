<?php

namespace Assegai\Core\GraphQL;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\IHttpArgumentsHost;

class GraphQLArgumentsHost implements IHttpArgumentsHost
{
  /**
   * @return Request
   */
  public function getRequest(): Request
  {
    // TODO: Implement getRequest() method.
    return Request::getInstance();
  }

  /**
   * @return Response
   */
  public function getResponse(): Response
  {
    // TODO: Implement getResponse() method.
    return Response::getInstance();
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
    // TODO: Implement getArgs() method.
    return $argv;
  }
}