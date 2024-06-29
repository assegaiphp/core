<?php

namespace Assegai\Core\Interfaces;

/**
 * Interface SingletonInterface
 *
 * @package Assegai\Core\Interfaces
 */
interface SingletonInterface
{
  /**
   * Get the instance of the class.
   *
   * @return static
   */
  public static function getInstance(): self;
}