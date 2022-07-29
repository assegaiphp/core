<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\ExecutionContext;
use Assegai\Core\Interfaces\IAssegaiInterceptor;

class InterceptorsConsumer
{
  /**
   * @param IAssegaiInterceptor[] $interceptors
   * @param ExecutionContext $context
   * @return ExecutionContext
   */
  public function intercept(array $interceptors, ExecutionContext $context): ExecutionContext
  {
    foreach ($interceptors as $interceptor)
    {
      $context = $interceptor->intercept(context: $context);
    }

    return $context;
  }
}