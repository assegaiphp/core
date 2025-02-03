<?php

namespace Assegai\Core\Consumers;

use Assegai\Core\ExecutionContext;
use Assegai\Core\Interfaces\ICanActivate;

/**
 * Consumes guards.
 */
final class GuardsConsumer
{
  /**
   * The singleton instance.
   *
   * @var ?self
   */
  private static ?self $instance = null;

  /**
   * Constructs a new GuardsConsumer instance.
   */
  private final function __construct()
  {
  }

  /**
   * Returns the singleton instance of the GuardsConsumer.
   *
   * @return static The singleton instance of the GuardsConsumer.
   */
  public static function getInstance(): self
  {
    if (! self::$instance)
    {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Determines if all guards can activate.
   *
   * @param ICanActivate[] $guards The guards.
   * @param ExecutionContext $context The execution context.
   * @return bool True if all guards can activate, false otherwise.
   */
  public function canActivate(array $guards, ExecutionContext $context): bool
  {
    foreach ($guards as $guard) {
      if (is_array($guard)) {
        if (! $this->canActivate(guards: $guard, context: $context) ) {
          return false;
        }
      } else if (! $guard->canActivate(context: $context)) {
        return false;
      }
    }

    return true;
  }
}