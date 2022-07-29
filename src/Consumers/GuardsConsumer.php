<?php

namespace Assegai\Core\Consumers;

use Assegai\Core\ExecutionContext;
use Assegai\Core\Interfaces\ICanActivate;

final class GuardsConsumer
{
  private static ?self $instance = null;

  private final function __construct()
  {
  }

  public static function getInstance(): self
  {
    if (! self::$instance)
    {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * @param ICanActivate[] $guards
   * @param ExecutionContext $context
   * @return bool
   */
  public function canActivate(array $guards, ExecutionContext $context): bool
  {
    foreach ($guards as $guard)
    {
      if (! $guard->canActivate(context: $context))
      {
        return false;
      }
    }

    return true;
  }
}