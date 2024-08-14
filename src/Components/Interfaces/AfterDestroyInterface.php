<?php

namespace Assegai\Core\Components\Interfaces;

/**
 * Interface AfterDestroyInterface.
 *
 * @package Assegai\Core\Components\Interfaces
 */
interface AfterDestroyInterface
{
  /**
   * Should be called after the object is destroyed.
   *
   * @return void
   */
  public function afterDestroy(): void;
}