<?php

namespace Assegai\Core\Http\Responses\Emitters;

use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;

class OpenSwooleResponseEmitter implements ResponseEmitterInterface
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
    if (method_exists($this->target, 'isWritable') && !$this->target->isWritable()) {
      return;
    }

    if ($response) {
      if (method_exists($this->target, 'status')) {
        $this->target->status($response->getStatusCode());
      }

      foreach ($response->getHeaders() as $header) {
        if (method_exists($this->target, 'header')) {
          $this->target->header($header['name'], $header['value']);
        }
      }
    }

    if (method_exists($this->target, 'end')) {
      $this->target->end($body);
    }
  }
}
