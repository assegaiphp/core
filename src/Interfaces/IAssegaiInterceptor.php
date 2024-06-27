<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\ExecutionContext;

/**
 * Interface describing implementation of an interceptor.
 *
 * @see [Interceptors](https://docs.assegaiphp.com/interceptors)
 * @package Assegai\Core\Interfaces
 */
interface IAssegaiInterceptor
{
  /**
   * Intercepts the request and returns a callable if the interceptor
   * is to be executed.
   *
   * @param ExecutionContext $context The execution context.
   * @return callable|null The callable to be executed or null if the interceptor
   * is not to be executed.
   */
  public function intercept(ExecutionContext $context): ?callable;
}