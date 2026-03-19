<?php

namespace Assegai\Core\Http\Responses\Emitters;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;

class PhpResponseEmitter implements ResponseEmitterInterface
{
  /**
   * @inheritDoc
   * @throws HttpException
   */
  public function emit(string $body, ?ResponseInterface $response = null): never
  {
    if ($response) {
      if (false === http_response_code($response->getStatusCode())) {
        throw new HttpException("Failed to set HTTP status code to {$response->getStatusCode()}");
      }

      $response->sendHeaders();
    }

    echo $body;
    exit;
  }
}
