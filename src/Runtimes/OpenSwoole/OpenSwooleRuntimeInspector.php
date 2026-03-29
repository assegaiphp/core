<?php

namespace Assegai\Core\Runtimes\OpenSwoole;

use RuntimeException;

class OpenSwooleRuntimeInspector
{
  public function isAvailable(): bool
  {
    return $this->getAvailabilityError() === null;
  }

  public function getAvailabilityError(): ?string
  {
    if (!extension_loaded('openswoole')) {
      return 'The OpenSwoole runtime requires the openswoole PHP extension. Install and enable ext-openswoole before serving with --runtime=openswoole.';
    }

    if (!class_exists('\\OpenSwoole\\HTTP\\Server')) {
      return 'The OpenSwoole HTTP server class is not available in the current PHP runtime.';
    }

    return null;
  }

  public function assertAvailable(): void
  {
    $error = $this->getAvailabilityError();

    if ($error !== null) {
      throw new RuntimeException($error);
    }
  }
}
