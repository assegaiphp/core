<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Exceptions\Http\InternalServerErrorException;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Interfaces;
use Assegai\Core\Http\Responses\Response;

class DefaultResponder implements Interfaces\ResponderInterface
{
  /**
   * @inheritDoc
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): never
  {
    if (! headers_sent()) {
      header('Content-Type: text/html');
    }

    if ($response instanceof Response) {
      $responseBody = $response->getBody();

      if (is_scalar($responseBody)) {
        exit($responseBody);
      }
    }

    if (is_scalar($response)) {
      exit($response);
    }

    throw new InternalServerErrorException('Invalid response type');
  }
}