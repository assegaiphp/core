<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Exceptions\Http\InternalServerErrorException;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\ApiResponse;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Orm\Queries\QueryBuilder\Results\DeleteResult;
use Assegai\Orm\Queries\QueryBuilder\Results\InsertResult;
use Assegai\Orm\Queries\QueryBuilder\Results\UpdateResult;

class JsonResponder implements ResponderInterface
{

  /**
   * @inheritDoc
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): never
  {
    if (! headers_sent()) {
      header('Content-Type: application/json');
    }

    if ($response instanceof Response) {
      $responseBody = $response->getBody();

      if (is_object($responseBody) || is_array($responseBody)) {

        if ($responseBody instanceof UpdateResult || $responseBody instanceof InsertResult) {
          if (method_exists($responseBody, 'getData')) {
            exit(json_encode($responseBody->getData()));
          }
        }

        if ($responseBody instanceof DeleteResult) {
          exit($responseBody->affected);
        }

        if (is_array($responseBody)) {
          exit(new ApiResponse($responseBody));
        }

        exit(json_encode($responseBody));
      }
    }

    if (is_object($response) || is_array($response)) {
      exit(json_encode($response));
    }

    throw new InternalServerErrorException('Invalid response type');
  }
}