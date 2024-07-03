<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;

/**
 * Class ClosureResponder
 *
 * @package Assegai\Core\Http\Responses\Responders
 */
class ClosureResponder implements ResponderInterface
{
  /**
   * @inheritDoc
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): never
  {
    // TODO: Implement respond() method.
  }
}