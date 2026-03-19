<?php

namespace Assegai\Core\Http\Responses\Interfaces;

interface ResponseEmitterInterface
{
  /**
   * Emits the prepared response body to the active PHP runtime.
   *
   * @param string $body
   * @param ResponseInterface|null $response
   * @return void
   */
  public function emit(string $body, ?ResponseInterface $response = null): void;
}
