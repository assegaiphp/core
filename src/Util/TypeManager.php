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
   * @param stdClass $object
   * @param string $targetType
   * @return mixed
   * @throws EntryNotFoundException
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