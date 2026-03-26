<?php

namespace Assegai\Core;

use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\Runtimes\OpenSwooleHttpRuntime;
use Assegai\Core\Runtimes\PhpHttpRuntime;
use Assegai\Core\Routing\Router;
use InvalidArgumentException;

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
    $injector = Injector::createFresh();
    $moduleManager = ModuleManager::createFresh($injector);
    $controllerManager = ControllerManager::createFresh($moduleManager);
    $router = Router::createFresh($injector, $controllerManager, $moduleManager);

    return new App(
      rootModuleClass: $moduleName,
      router: $router,
      controllerManager: $controllerManager,
      moduleManager: $moduleManager,
      injector: $injector,
      runtime: $runtime ?? new PhpHttpRuntime(),
    );
  }

  /**
   * Creates an app using a named runtime.
   *
   * @param string $moduleName
   * @param string $runtime
   * @return App
   */
  public static function createWithRuntime(string $moduleName, string $runtime): App
  {
    return self::create($moduleName, self::resolveRuntime($runtime));
  }

  /**
   * Resolves a named runtime into a runtime instance.
   *
   * @param string $runtime
   * @return HttpRuntimeInterface
   */
  public static function resolveRuntime(string $runtime): HttpRuntimeInterface
  {
    return match (strtolower(trim($runtime))) {
      'php' => new PhpHttpRuntime(),
      'openswoole', 'swoole' => new OpenSwooleHttpRuntime(),
      default => throw new InvalidArgumentException("Unsupported runtime [$runtime]."),
    };
  }
}
