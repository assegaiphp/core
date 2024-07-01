<?php

namespace Assegai\Core\Util;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Injector;
use DateTime;
use Exception;
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
   * @throws \ReflectionException
   */
  public static function castObjectToUserType(stdClass $object, string $targetType): mixed
  {
    if (str_contains($targetType, '|')) {
      $targetTypes = explode('|', $targetType);
      foreach ($targetTypes as $type) {
        try {
          return self::castObjectToUserType($object, $type);
        } catch (EntryNotFoundException $e) {
          continue;
        }
      }
      throw new EntryNotFoundException($targetType);
    }

    if ($targetType === 'object') {
      $targetType = stdClass::class;
    }

    $instance = new $targetType;
    $injector = Injector::getInstance();

    if (! class_exists($targetType) ) {
      throw new EntryNotFoundException($targetType);
    }

    if (get_class($object) === $targetType) {
      return $object;
    }

    foreach ($object as $key => $value) {
      if (property_exists($instance, $key)) {
        $propertyReflection = new ReflectionProperty($instance, $key);
        if ($propertyReflection->getType()->getName() === 'DateTime') {
          try {
            $instance->$key = new DateTime($value);
          } catch (Exception $e) {
            throw new HttpException($e->getMessage(), HttpStatus::BadRequest());
          }
        } elseif (enum_exists($enum = $propertyReflection->getType()->getName())) {
          $reflectionEnum = new \ReflectionEnum($enum);

          $instance->$key = match(true) {
            $reflectionEnum->getBackingType()->getName() === gettype($value) => $enum::tryFrom($value),
          };
        } else {
          $instance->$key = $value;
        }
      }
    }

    return $instance;
  }
}