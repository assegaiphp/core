<?php

namespace Assegai\Core\Common;

use Closure;

class DynamicModuleOptions
{
  /**
   * @param int|null $retryAttempts
   * @param int|null $retryDelay
   * @param Closure|null $toRetry
   * @param bool|null $autoLoadEntities
   * @param bool|null $verboseRetryLog
   */
  public function __construct(
    public readonly ?int     $retryAttempts = 10,
    public readonly ?int     $retryDelay = 3000,
    public readonly ?Closure $toRetry = null,
    public readonly ?bool    $autoLoadEntities = false,
    public readonly ?bool    $verboseRetryLog = false,
  )
  {
  }
}