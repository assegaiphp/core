<?php

namespace Assegai\Core\Util;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
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
   * @throws HttpException If the object cannot be cast to the target type.
   */
  public static function castObjectToUserType(stdClass $object, string $targetType): mixed
  {
    $instance = new $targetType;
    $injector = Injector::getInstance();

    if (!class_exists($targetType)) {
      throw new EntryNotFoundException($targetType);
    }

    if (get_class($object) === $targetType)
    {
      return $object;
    }

    foreach ($object as $key => $value)
    {
      if (property_exists($instance, $key))
      {
        $propertyReflection = new ReflectionProperty($instance, $key);
        if ($propertyReflection->getType()->getName() === 'DateTime')
        {
          try
          {
            $instance->$key = new DateTime($value);
          }
          catch (\Exception $e)
          {
            throw new HttpException($e->getMessage(), HttpStatus::BadRequest());
          }
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