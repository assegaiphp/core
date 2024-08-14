<?php

namespace Assegai\Core\Components\Interfaces;

/**
 * Interface AfterInitInterface.
 *
 * @package Assegai\Core\Components\Interfaces
 */
interface AfterInitInterface
{
  /**
   * Should be called after the object is initialized.
   *
   * @return void
   */
  public function afterInit(): void;
}