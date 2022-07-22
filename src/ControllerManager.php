<?php

namespace Assegai\Core;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Attributes\Controller;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;

class ControllerManager
{
  protected static ?ControllerManager $instance = null;

  /** @var ReflectionAttribute[] $lastLoadedAttributes */
  protected array $lastLoadedAttributes = [];

  /** @var ReflectionClass[] $controllerTokensList */
  protected array $controllerTokensList = [];

  protected array $controllerPathTokenIdMap = [];

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

  /**
   * @return array
   */
  public function getControllerTokenList(): array
  {
    return $this->controllerTokensList;
  }

  /**
   * @return array
   */
  public function getControllerPathTokenIdMap(): array
  {
    return $this->controllerPathTokenIdMap;
  }

  /**
   * @param ReflectionAttribute[] $moduleTokensList
   * @return array
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
        if ($controller = $this->getControllerReflection($tokenId))
        {
          $this->controllerTokensList[$tokenId] = $controller;

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
   * @throws EntryNotFoundException
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