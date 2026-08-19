<?php

namespace Assegai\Core\Http\Cors;

use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Interfaces\FileResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;

/**
 * Applies CORS immediately before delegating file emission to the active runtime.
 */
final readonly class CorsFileResponseEmitter implements FileResponseEmitterInterface
{
  public function __construct(
    private FileResponseEmitterInterface $emitter,
    private RequestInterface $request,
    private CorsProcessor $processor,
  )
  {
  }

  public function emit(string $body, ?ResponseInterface $response = null): void
  {
    $this->applyCors($response);
    $this->emitter->emit($body, $response);
  }

  public function emitFile(string $filename, ?ResponseInterface $response = null): void
  {
    $this->applyCors($response);
    $this->emitter->emitFile($filename, $response);
  }

  private function applyCors(?ResponseInterface $response): void
  {
    if ($response) {
      $this->processor->apply($this->request, $response);
    }
  }
}
