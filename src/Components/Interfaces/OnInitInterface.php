<?php

namespace Assegai\Core\Components\Interfaces;

/**
 * Interface OnInitInterface.
 *
 * @package Assegai\Core\Components\Interfaces
 */
interface OnInitInterface
{
  /**
   * Should be called when the object is initialized.
   *
   * @return void
   */
  public function onInit(): void;
}