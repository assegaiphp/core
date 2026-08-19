<?php

namespace Assegai\Core\Http\Responses\Emitters;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\Responses\Interfaces\FileResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use RuntimeException;

class PhpResponseEmitter implements FileResponseEmitterInterface
{
  /**
   * @inheritDoc
   * @throws HttpException
   */
  public function emit(string $body, ?ResponseInterface $response = null): void
  {
    $this->emitMetadata($response);

    echo $body;
  }

  public function emitFile(string $filename, ?ResponseInterface $response = null): void
  {
    $stream = @fopen($filename, 'rb');

    if ($stream === false) {
      throw new RuntimeException('Unable to open the response file for streaming.');
    }

    try {
      $this->emitMetadata($response);
      fpassthru($stream);
    } finally {
      fclose($stream);
    }
  }

  /**
   * @throws HttpException
   */
  private function emitMetadata(?ResponseInterface $response): void
  {
    if (!$response) {
      return;
    }

    if (false === http_response_code($response->getStatusCode())) {
      throw new HttpException("Failed to set HTTP status code to {$response->getStatusCode()}");
    }

    $response->sendHeaders();
  }
}
