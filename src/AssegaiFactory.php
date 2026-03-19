<?php

namespace Assegai\Core;

use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\Runtimes\PhpHttpRuntime;
use Assegai\Core\Routing\Router;

final class AssegaiFactory
{
  private final function __construct()
  {
  }

  /**
   * @param string $moduleName
   * @param HttpRuntimeInterface|null $runtime
   * @return App
   */
  public static function create(string $moduleName, ?HttpRuntimeInterface $runtime = null): App
  {
    return new App(
      rootModuleClass: $moduleName,
      router: Router::getInstance(),
      controllerManager: ControllerManager::getInstance(),
      moduleManager: ModuleManager::getInstance(),
      injector: Injector::getInstance(),
      runtime: $runtime ?? new PhpHttpRuntime(),
    );
  }
}
