<?php

namespace Assegai\Core\Interceptors;

use Closure;

class FileInterceptorOptions
{
  public function __construct(
    public readonly string $dest = DEFAULT_STORAGE_PATH,
    public readonly string $storage = DEFAULT_STORAGE_PATH,
    public readonly ?Closure $fileFilter = null,
    public readonly array $limits = [],
    public readonly bool $preservePath = false
  )
  {
  }
}