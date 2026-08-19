<?php

namespace Assegai\Core\Http\Responses\Interfaces;

/**
 * Emits file-backed response bodies without loading the whole file into PHP memory.
 */
interface FileResponseEmitterInterface extends ResponseEmitterInterface
{
  public function emitFile(string $filename, ?ResponseInterface $response = null): void;
}
