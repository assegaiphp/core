<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;

/**
 * Class ClosureResponder
 *
 * @package Assegai\Core\Http\Responses\Responders
 */
class ClosureResponder implements ResponderInterface
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
    if (is_callable($response)) {
      Responder::getInstance()->respond($response(), $code);
    }
  }
}
