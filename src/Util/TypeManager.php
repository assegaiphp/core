<?php

namespace Assegai\Core\Util;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Injector;
use DateTime;
use ReflectionProperty;
use stdClass;

class TypeManager
{
  private function __construct()
  {
  }

  /**
   * Casts an object of type stdClass to an object of a given type.
   *
   * @param stdClass $object The object to cast.
   * @param string $targetType The target type to cast to.
   * @return mixed The object cast to the user-defined type.
   * @throws EntryNotFoundException If the target type cannot be found.
   */
  public static function castObjectToUserType(stdClass $object, string $targetType): mixed
  {
    $instance = new $targetType;
    $injector = Injector::getInstance();

    if (! class_exists($targetType) )
    {
      throw new EntryNotFoundException($targetType);
    }

    foreach ($object as $key => $value)
    {
      if (property_exists($instance, $key))
      {
        $propertyReflection = new ReflectionProperty($instance, $key);
        if ($propertyReflection->getType()->getName() === 'DateTime')
        {
          $instance->$key = DateTime::createFromFormat(DATE_ATOM, $value);
        }
        else
        {
          $instance->$key = $value;
        }
      }
    }

    return $instance;
  }
}