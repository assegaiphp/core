<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Exceptions\Http\InternalServerErrorException;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\ApiResponse;
use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Response;
use Throwable;

class JsonResponder implements ResponderInterface
{
  public function __construct(
    protected ResponseEmitterInterface $emitter = new PhpResponseEmitter()
  )
  {
  }

  /**
   * @inheritDoc
   * @throws Throwable
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): void
  {
    if ($response instanceof Response) {
      $response->setContentType(\Assegai\Core\Enumerations\Http\ContentType::JSON);
      $responseBody = $response->getBody();

      if (!$response->shouldWrapJsonBody()) {
        $this->emitter->emit($this->encodePayload($responseBody), $response);
        return;
      }
    }

    if ($response instanceof Response) {
      if (is_array($responseBody)) {
        $this->emitter->emit((string)new ApiResponse($responseBody), $response);
        return;
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
          $this->emitter->emit((string)new ApiResponse($responseBody->getData(), $total), $response);
          return;
        }

        if ( str_contains($responseBodyClassName, 'UpdateResult') || str_contains($responseBodyClassName, 'InsertResult') ) {
          if (method_exists($responseBody, 'getData')) {
            $this->emitter->emit((string)new ApiResponse($responseBody->getData()), $response);
            return;
          }
        }

        if ( str_contains($responseBodyClassName, 'DeleteResult') ) {
          $request = Request::current();
          $this->emitter->emit(json_encode([
            'params' => implode($request->getParams()),
            'affected' => $responseBody->affected
          ]), $response);
          return;
        }

        if (is_array($responseBody)) {
          $this->emitter->emit((string)new ApiResponse($responseBody), $response);
          return;
        }

        $this->emitter->emit((string)new ApiResponse($responseBody), $response);
        return;
      }
    }

    if (is_object($response) || is_array($response)) {
      $emissionResponse = Response::current();
      $emissionResponse->setContentType(\Assegai\Core\Enumerations\Http\ContentType::JSON);
      $this->emitter->emit(
        (string)new ApiResponse(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)),
        $emissionResponse
      );
      return;
    }

    throw new InternalServerErrorException('Invalid response type');
  }

  /**
   * @param string|array|object $payload
   * @return string
   */
  private function encodePayload(string|array|object $payload): string
  {
    if (is_string($payload)) {
      return $payload;
    }

    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  }
}
