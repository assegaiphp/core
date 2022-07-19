<?php

namespace Assegai\Core;

class Router
{
  private static ?Router $instance = null;

  private final function __construct()
  {
  }

  public static function getInstance(): Router
  {
    if (!self::$instance)
    {
      self::$instance = new Router();
    }

    return self::$instance;
  }
}