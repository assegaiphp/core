<?php

namespace Assegai\Core\Util;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Injector;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionEnum;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * The TypeManager class. This class is responsible for managing types.
 *
 * @package Assegai\Core\Util
 */
class TypeManager
{
  protected LoggerInterface $logger;

  /**
   * Constructs a TypeManager.
   */
  private function __construct()
  {
    $this->logger = new ConsoleLogger(new ConsoleOutput());
  }

  /**
   * Casts an object of type stdClass to an object of a given type.
   *
   * @param stdClass $object The object to cast.
   * @param string $targetType The target type to cast to.
   * @return mixed The object cast to the user-defined type.
   * @throws EntryNotFoundException If the target type cannot be found.
   * @throws HttpException If the object cannot be cast to the target type.
   * @throws ReflectionException
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

    if (!class_exists($targetType)) {
      throw new EntryNotFoundException($targetType);
    }

    if (get_class($object) === $targetType) {
      return $object;
    }

    foreach ($object as $key => $value) {
      if (property_exists($instance, $key)) {
        $propertyReflection = new ReflectionProperty($instance, $key);
        $propertyReflectionType = $propertyReflection->getType();

        if ($propertyReflectionType instanceof ReflectionUnionType) {
          $valueType = strtolower(gettype($value));

          if (!str_contains(strval($propertyReflectionType), $valueType)) {
            continue;
          }

          $instance->$key = $value;
        } else {
          $instance->$key = self::getValidPropertyValue($propertyReflectionType, $key, $value);
        }
      }
    }

    return $instance;
  }

  protected static function getValidPropertyValue(
    null|ReflectionIntersectionType|ReflectionNamedType|ReflectionUnionType $propertyReflectionType,
    int|string $key,
    mixed $value
  ): mixed
  {
    if ($propertyReflectionType->getName() === 'DateTime') {
      try {
        return new DateTime($value);
      } catch (Exception $e) {
        throw new HttpException($e->getMessage(), HttpStatus::BadRequest());
      }
    } elseif (enum_exists($enum = $propertyReflectionType)) {
      $reflectionEnum = new ReflectionEnum($enum);

      return match (true) {
        $reflectionEnum->getBackingType()->getName() === gettype($value) => $enum::tryFrom($value),
      };
    }

    return $value;
  }
}