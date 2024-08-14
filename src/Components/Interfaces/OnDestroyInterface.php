<?php

namespace Assegai\Core\Components\Interfaces;

/**
 * Interface OnDestroyInterface.
 *
 * @package Assegai\Core\Components\Interfaces
 */
interface OnDestroyInterface
{
  /**
   * Should be called when the object is destroyed.
   *
   * @return void
   */
  public function onDestroy(): void;
}