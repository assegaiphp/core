<?php

namespace Assegai\Core\Interfaces;

interface ICanActivate
{
  /**
   * @param IExecutionContext $context
   * @return bool
   */
  public function canActivate(IExecutionContext $context): bool;
}