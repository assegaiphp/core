<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Exceptions\Http\InternalServerErrorException;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\ApiResponse;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Response;
use Throwable;

class JsonResponder implements ResponderInterface
{

  /**
   * @inheritDoc
   * @throws Throwable
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): never
  {
    if (! headers_sent()) {
      header('Content-Type: application/json');
    }

    if ($response instanceof Response) {
      $responseBody = $response->getBody();

      if (is_array($responseBody)) {
        exit(new ApiResponse($responseBody));
      }

      if (is_object($responseBody)) {
        $responseBodyClassName = get_class($responseBody);

        if (method_exists($responseBody, 'isError') && $responseBody->isError()) {
          if (method_exists($responseBody, 'getErrors')) {
            $lastError = array_first($responseBody->getErrors());

            if ($lastError instanceof Throwable) {
              throw $lastError;
            }
          }
        }


        if ( str_contains($responseBodyClassName, 'FindResult') ) {
          $total = method_exists($responseBody, 'getTotal') ? $responseBody->getTotal() : null;
          exit(new ApiResponse($responseBody->getData(), $total));
        }

        if ( str_contains($responseBodyClassName, 'UpdateResult') || str_contains($responseBodyClassName, 'InsertResult') ) {
          if (method_exists($responseBody, 'getData')) {
            exit(new ApiResponse($responseBody->getData()));
          }
        }

        if ( str_contains($responseBodyClassName, 'DeleteResult') ) {
          $request = Request::getInstance();
          exit(json_encode([
            'params' => implode($request->getParams()),
            'affected' => $responseBody->affected
          ]));
        }

        if (is_array($responseBody)) {
          exit(new ApiResponse($responseBody));
        }

        exit(new ApiResponse($responseBody));
      }
    }

    if (is_object($response) || is_array($response)) {
      exit(new ApiResponse(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)));
    }

    throw new InternalServerErrorException('Invalid response type');
  }
}