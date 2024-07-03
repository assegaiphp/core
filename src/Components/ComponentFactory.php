<?php

namespace Assegai\Core\Components;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Injector;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Factory class for creating components. Components are classes that are used to render views.
 *
 * @package Assegai\Core\Components

 */
final class ComponentFactory
{
  /**
   * Creates a component instance.
   *
   * @param string $componentClass
   * @return object The component instance.
   * @throws ReflectionException If the component class does not exist.
   * @throws ContainerException If the component constructor parameters cannot be resolved.
   */
  public static function createComponent(string $componentClass): object
  {
    $injector = Injector::getInstance();

    # Create reflection
    $componentReflection = new ReflectionClass($componentClass);

    if (! self::isValidComponentClass($componentReflection) ) {
      throw new InvalidArgumentException('Invalid component class');
    }

    # Get constructor parameters
    $dependencies = [];
    $componentConstructorParams = $componentReflection->getConstructor()?->getParameters() ?? [];

    # Resolve constructor parameters
    foreach ($componentConstructorParams as $constructorParam) {
      $dependencies[] = $injector->resolve($constructorParam->getType()->getName());
    }

    # Create component instance
    return $componentReflection->newInstanceArgs($dependencies) ?? throw new InvalidArgumentException('Invalid component class');
  }

  /**
   * Checks if the given class is a valid component class.
   *
   * @param ReflectionClass $componentClass
   * @return bool True if the given reflection resolves to a valid component instance, otherwise false
   */
  private static function isValidComponentClass(ReflectionClass $componentClass): bool
  {
    $componentAttributes = $componentClass->getAttributes(Component::class);

    return ! empty($componentAttributes);
  }
}