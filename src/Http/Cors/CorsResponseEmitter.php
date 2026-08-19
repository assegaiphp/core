<?php

namespace Assegai\Core\Http\Cors;

use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;

/**
 * Applies CORS immediately before delegating response emission to the active runtime.
 */
final readonly class CorsResponseEmitter implements ResponseEmitterInterface
{
  public function __construct(
    private ResponseEmitterInterface $emitter,
    private RequestInterface $request,
    private CorsProcessor $processor,
  )
  {
  }

  public function emit(string $body, ?ResponseInterface $response = null): void
  {
    if ($response) {
      $this->processor->apply($this->request, $response);
    }

    $this->emitter->emit($body, $response);
  }
}
