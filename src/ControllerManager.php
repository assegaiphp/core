<?php

namespace Assegai\Core;

class ControllerManager
{
  protected static ?ControllerManager $instance = null;

  private final function __construct()
  {
  }

  /**
   * @return ControllerManager
   */
  public static function getInstance(): ControllerManager
  {
    if (empty(self::$instance))
    {
      self::$instance = new ControllerManager();
    }

    return self::$instance;
  }
}