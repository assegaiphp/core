<?php

namespace Assegai\Core\Exceptions\Handlers\Concerns;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;

trait EmitsErrorResponses
{
  protected function emitErrorResponse(string $body, ContentType $contentType, int $statusCode): void
  {
    $response = Response::current();
    $response->reset();
    $response->setStatus($statusCode);
    $response->setContentType($contentType);
    $response->setHeader('Content-Type', $contentType->value);
    $response->setBody($body);

    $emitter = Injector::getInstance()->get(ResponseEmitterInterface::class);

    if (!$emitter instanceof ResponseEmitterInterface) {
      $emitter = new PhpResponseEmitter();
    }

    $emitter->emit($body, $response);
  }
}
