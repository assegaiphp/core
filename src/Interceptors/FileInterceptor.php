<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Interfaces\IAssegaiInterceptor;

#[Injectable]
class FileInterceptor implements IAssegaiInterceptor
{
  public function __construct(public readonly string $name)
  {
  }

  public function intercept(ExecutionContext $context): ?callable
  {
    // TODO: Implement intercept() method.
    return null;
  }
}