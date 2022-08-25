<?php

namespace Assegai\Core\Interfaces;

/**
 * A lifecycle hook that is called after route has been guarded, i.e. after a guard returns false.
 */
interface IOnGuard
{
  public function onGuard(IExecutionContext $context): void;
}