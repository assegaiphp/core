<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Exceptions\Http\InternalServerErrorException;
use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Response;

class DefaultResponder implements Interfaces\ResponderInterface
{
  public function __construct(
    protected ResponseEmitterInterface $emitter = new PhpResponseEmitter()
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): void
  {
    if ($response instanceof Response) {
      $responseBody = $response->getBody();

      if (is_scalar($responseBody)) {
        $this->emitter->emit((string)$responseBody, $response);
        return;
      }
    }

    if (is_scalar($response)) {
      $emissionResponse = Response::current();
      $emissionResponse->setContentType(\Assegai\Core\Enumerations\Http\ContentType::HTML);
      $this->emitter->emit((string)$response, $emissionResponse);
      return;
    }

    throw new InternalServerErrorException('Invalid response type');
  }
}
