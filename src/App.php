<?php

namespace Assegai\Core;

use Assegai\Core\Http\Request;

/**
 * @since 1.0.0
 * @version 1.0.0
 * @author Andrew Masiye <amasiye313@gmail.com>
 *
 * @link https://docs.assegaiphp.com
 */
class App
{
  protected array $providers = [];
  protected array $controllers = [];
  protected Request $request;

  /**
   * @param string $rootModuleClass
   * @param Router $router
   * @param ControllerManager $controllerManager
   * @param ModuleManager $moduleManager
   */
  public function __construct(
    protected readonly string $rootModuleClass,
    protected readonly Router $router,
    protected readonly ControllerManager $controllerManager,
    protected readonly ModuleManager $moduleManager
  )
  {
    $this->request = Request::getInstance();
    $this->request->setApp($this);
  }

  /**
   * @return void
   */
  public function run(): void
  {
    $this->resolveModules();
    $this->resolveControllers();
    $this->handleRequest();
  }

  /**
   * @return void
   */
  public function resolveModules(): void
  {
    echo 'Loading modules' . PHP_EOL;
  }

  /**
   * @return void
   */
  private function resolveControllers(): void
  {
    echo 'Loading controllers' . PHP_EOL;
  }

  /**
   * @return void
   */
  private function handleRequest(): void
  {
    echo "Loading request: $this->request" . PHP_EOL;
  }
}