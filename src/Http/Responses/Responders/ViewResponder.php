<?php

namespace Assegai\Core\Http\Responses\Responders;

use Assegai\Core\Http\HttpStatusCode;
use Assegai\Core\Http\Responses\Interfaces;

class ViewResponder implements Interfaces\ResponderInterface
{

  /**
   * @inheritDoc
   */
  public function respond(mixed $response, int|HttpStatusCode|null $code = null): never
  {
    // TODO: Implement respond() method.
  }
}