<?php

namespace Assegai\Core;

use Assegai\Core\Routing\Router;

final class AssegaiFactory
{
  private final function __construct()
  {
  }

  /**
   * @param string $moduleName
   * @return App
   */
  public static function create(string $moduleName): App
  {
    return new App(
      rootModuleClass: $moduleName,
      router: Router::getInstance(),
      controllerManager: ControllerManager::getInstance(),
      moduleManager: ModuleManager::getInstance(),
      injector: Injector::getInstance(),
    );
  }
}