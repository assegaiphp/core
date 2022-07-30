<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\ExecutionContext;

/**
 * Interface describing implementation of an interceptor.
 *
 * @see [Interceptors](https://docs.assegaiphp.com/interceptors)
 *
 */
interface IAssegaiInterceptor
{
  public function intercept(ExecutionContext $context): ?callable;
}