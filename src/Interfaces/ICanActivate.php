<?php

namespace Assegai\Core\Interfaces;

interface ICanActivate
{
  /**
   * @param IExcecutionContext $context
   * @return bool
   */
  public function canActivate(IExcecutionContext $context): bool;
}