<?php

namespace Assegai\Core\Interfaces;

interface IActivateable
{
  /**
   * @param IExcecutionContext $context
   * @return bool
   */
  public function canActivate(IExcecutionContext $context): bool;
}