<?php

namespace Assegai\Core\Components\Interfaces;

/**
 * This interface is used to define a lifecycle hook that is called after the properties of a component have been bound.
 *
 * @package Assegai\Core\Components\Interfaces
 */
interface AfterPropertiesBoundInterface
{
  /**
   * This method is called after the properties of a component have been bound.
   *
   * @return void
   */
  public function afterPropertiesBound(): void;
}