<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\Util\Paths;
use Closure;

class FileInterceptorOptions
{
  public readonly string $dest;
  public readonly string $storage;
  /**
   * @param array{fileSize?: int, size?: int} $limits
   */
  public function __construct(
    ?string $dest = null,
    ?string $storage = null,
    public readonly ?Closure $fileFilter = null,
    public readonly array $limits = [],
    public readonly bool $preservePath = false
  )
  {
    $this->dest = $dest ?? Paths::getUploadDirectory();
    $this->storage = $storage ?? Paths::getUploadDirectory();
  }
}
