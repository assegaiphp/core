<?php

namespace Assegai\Core;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Attributes\Controller;
use Assegai\Core\Exceptions\Http\NotFoundException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * The ControllerManager class is responsible for managing the controllers.
 *
 * @package Assegai\Core
 */
class ControllerManager
{
  protected ModuleManager $moduleManager;
  /**
   * @var ControllerManager|null
   */
  protected static ?ControllerManager $instance = null;

  /** @var ReflectionAttribute[] $lastLoadedAttributes */
  protected array $lastLoadedAttributes = [];

  /** @var ReflectionClass[] $controllerTokensList */
  protected array $controllerTokensList = [];

  /** @var array $controllerPathTokenIdMap */
  protected array $controllerPathTokenIdMap = [];

  /**
   * ControllerManager constructor.
   */
  private final function __construct()
  {
    $this->moduleManager = ModuleManager::getInstance();
  }

  /**
   * Returns the ControllerManager instance.
   *
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

  /**
   * Returns the controller token list.
   *
   * @return array<string, ReflectionClass>
   */
  public function getControllerTokenList(): array
  {
    return $this->controllerTokensList;
  }

  /**
   * Returns the controller path token id map.
   *
   * @return array<string, string>
   */
  public function getControllerPathTokenIdMap(): array
  {
    return $this->controllerPathTokenIdMap;
  }

  /**
   * Returns the root controller class.
   *
   * @return string
   * @throws ReflectionException
   */
  public function getRootControllerClass(): string
  {
    $rootModuleClass = $this->moduleManager->getRootModuleClass();
    $rootModuleReflection = new ReflectionClass($rootModuleClass);
    $attributes = $rootModuleReflection->getAttributes(Module::class);

    if (! $attributes )
    {
      throw new RuntimeException('Root module class must be decorated with the Module attribute');
    }

    /** @var ReflectionAttribute $moduleAttributeReflection */
    $moduleAttributeReflection = array_pop($attributes);

    $rootControllersClasses = $moduleAttributeReflection->getArguments()['controllers'] ?? [];

    if (empty($rootControllersClasses))
    {
      throw new RuntimeException('Root module class must have at least one controller');
    }

    $rootControllerClass = '';

    foreach ($rootControllersClasses as $index => $controllersClass)
    {
      if ($index === 0)
      {
        $rootControllerClass = $controllersClass;
      }

      // Check if the controller has a path === '/'
      if (isset($this->controllerPathTokenIdMap[$controllersClass]) && $this->controllerPathTokenIdMap[$controllersClass] === '/')
      {
        $rootControllerClass = $controllersClass;
        break;
      }
    }

    return $rootControllerClass;
  }

  /**
   * Builds the controller tokens list. The controller tokens list is a list of all the controllers in the application.
   *
   * @param ReflectionAttribute[] $moduleTokensList The list of module tokens.
   * @return array<string, ReflectionClass> The controller tokens list.
   * @throws EntryNotFoundException
   */
  public function buildControllerTokensList(array $moduleTokensList): array
  {
    foreach ($moduleTokensList as $index => $module)
    {
      /** @var ['imports' => 'array', 'exports' => 'array', 'providers' => 'array'] $args */
      $args = $module->getArguments();

      foreach ($args['controllers'] as $tokenId)
      {
        if ($controllerReflection = $this->getControllerReflection($tokenId))
        {
          $this->controllerTokensList[$tokenId] = $controllerReflection;

          if (!empty($this->lastLoadedAttributes))
          {
            $controllerAttribute = array_pop($this->lastLoadedAttributes);
            $data = $controllerAttribute->getArguments();
            if (! empty($data))
            {
              $path = array_pop($data);
              $this->controllerPathTokenIdMap[$tokenId] = $path;
            }
          }
        }
      }
    }

    return $this->getControllerTokenList();
  }

  /**
   * Returns the controller reflection from the given token ID.
   *
   * @param string $tokenId The token ID of the controller.
   * @return ReflectionClass|null The controller reflection.
   * @throws EntryNotFoundException If the controller is not found.
   */
  private function getControllerReflection(string $tokenId): ?ReflectionClass
  {
    try {
      $reflectionClass = new ReflectionClass($tokenId);
      $this->lastLoadedAttributes = $reflectionClass->getAttributes(Controller::class);
      return (! empty($this->lastLoadedAttributes) ) ? $reflectionClass : null;
    } catch (ReflectionException)
    {
      throw new EntryNotFoundException($tokenId);
    }
  }
}