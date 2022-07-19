<?php

namespace Assegai\Core;

final class AssegaiFactory
{
  private final function __construct()
  {
  }

  public static function create(string $moduleName): App
  {
    return new App(
      rootModuleClass: $moduleName,
      router: Router::getInstance(),
      controllerManager: ControllerManager::getInstance(),
      moduleManager: ModuleManager::getInstance()
    );
  }
}