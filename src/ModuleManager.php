<?php

namespace Assegai\Core;

use ReflectionClass;
use ReflectionException;

class ModuleManager
{
  protected static ?ModuleManager $instance = null;

  private final function __construct()
  {
  }

  /**
   * @return ModuleManager
   */
  public static function getInstance(): ModuleManager
  {
    if (empty(self::$instance))
    {
      self::$instance = new ModuleManager();
    }

    return self::$instance;
  }

  public function getControllers(string $moduleClass): array
  {
    $controllers = [];

    try
    {
      $refClass = new ReflectionClass($moduleClass);
    }
    catch (ReflectionException $e)
    {
      echo $e->getMessage() . PHP_EOL;
    }

    return $controllers;
  }
}