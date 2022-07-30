<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\ExecutionContext;
use Assegai\Core\Interfaces\IAssegaiInterceptor;

final class InterceptorsConsumer
{
  private static ?self $instance = null;
  private final function __construct()
  {}

  public static function getInstance(): self
  {
    if (! self::$instance )
    {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * @param IAssegaiInterceptor[] $interceptors
   * @param ExecutionContext $context
   * @return callable[]
   */
  public function intercept(array $interceptors, ExecutionContext $context): array
  {
    $callHandlers = [];
    foreach ($interceptors as $interceptor)
    {
      if ($result = $interceptor->intercept(context: $context))
      {
        if (is_callable($result))
        {
          $callHandlers[] = $result;
        }
      }
    }

    return $callHandlers;
  }
}