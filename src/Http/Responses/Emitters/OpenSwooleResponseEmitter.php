<?php

namespace Assegai\Core\Http\Responses\Emitters;

use Assegai\Core\Http\Responses\Interfaces\FileResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use RuntimeException;

class OpenSwooleResponseEmitter implements FileResponseEmitterInterface
{
  public function __construct(
    private readonly object $target,
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function emit(string $body, ?ResponseInterface $response = null): void
  {
    if (!$this->prepareResponse($response)) {
      return;
    }

    if (method_exists($this->target, 'end')) {
      $this->target->end($body);
    }
  }

  public function emitFile(string $filename, ?ResponseInterface $response = null): void
  {
    if (method_exists($this->target, 'isWritable') && !$this->target->isWritable()) {
      return;
    }

    if (!method_exists($this->target, 'sendfile')) {
      throw new RuntimeException('The active OpenSwoole response does not support file streaming.');
    }

    if (!$this->prepareResponse($response)) {
      return;
    }

    if ($this->target->sendfile($filename) === false) {
      throw new RuntimeException('OpenSwoole failed to stream the response file.');
    }
  }

  private function prepareResponse(?ResponseInterface $response): bool
  {
    if (method_exists($this->target, 'isWritable') && !$this->target->isWritable()) {
      return false;
    }

    if (!$response) {
      return true;
    }

    if (method_exists($this->target, 'status')) {
      $this->target->status($response->getStatusCode());
    }

    foreach ($response->getHeaders() as $header) {
      if (method_exists($this->target, 'header')) {
        $this->target->header($header['name'], $header['value']);
      }
    }

    return true;
  }
}
