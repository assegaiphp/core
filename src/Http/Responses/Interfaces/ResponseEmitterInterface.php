<?php

namespace Assegai\Core\Http\Responses\Interfaces;

interface ResponseEmitterInterface
{
  /**
   * Emits the prepared response body to the active PHP runtime.
   *
   * @param string $body
   * @param ResponseInterface|null $response
   * @return never
   */
  public function emit(string $body, ?ResponseInterface $response = null): never;
}
