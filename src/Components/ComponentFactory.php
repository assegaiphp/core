<?php

namespace Assegai\Core\Components;

use Assegai\Core\Components\Interfaces\ComponentInterface;

/**
 * Factory class for creating components. Components are classes that are used to render views.
 *
 * @package Assegai\Core\Components

 */
final class ComponentFactory
{
  public static function createComponent(string $componentClass): ComponentInterface
  {
    if (! self::isValidComponentClass($componentClass) ) {
      throw new \InvalidArgumentException('Invalid component class');
    }

    $component = null;

    return $component;
  }

  /**
   * Checks if the given class is a valid component class.
   *
   * @param string $componentClass
   * @return bool
   */
  private static function isValidComponentClass(string $componentClass): bool
  {
    return true;
  }
}