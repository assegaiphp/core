<?php

namespace Assegai\Core\Util;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatus;
use BackedEnum;
use DateTime;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use stdClass;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

/**
 * The TypeManager class. This class is responsible for managing types.
 *
 * @package Assegai\Core\Util
 */
class TypeManager
{
  protected LoggerInterface $logger;

  /**
   * @var array<class-string, array<string, ReflectionType|null>>
   */
  protected static array $propertyTypeCache = [];

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
          return self::castObjectToUserType($object, trim($type));
        } catch (EntryNotFoundException $e) {
          continue;
        }
      }

      throw new EntryNotFoundException($targetType);
    }

    $targetType = trim($targetType);

    if ($targetType === 'array') {
      return self::normalizeArrayValue($object);
    }

    if ($targetType === 'object') {
      $targetType = stdClass::class;
    }

    if (!class_exists($targetType)) {
      throw new EntryNotFoundException($targetType);
    }

    if (get_class($object) === $targetType) {
      return $object;
    }

    $instance = new $targetType;
    $propertyTypes = self::getPublicPropertyTypes($targetType);

    foreach (get_object_vars($object) as $key => $value) {
      if (array_key_exists($key, $propertyTypes)) {
        $instance->$key = self::getValidPropertyValue($propertyTypes[$key], $key, $value);
      }
    }

    return $instance;
  }

  /**
   * @param class-string $targetType
   * @return array<string, ReflectionType|null>
   * @throws ReflectionException
   */
  protected static function getPublicPropertyTypes(string $targetType): array
  {
    if (!array_key_exists($targetType, self::$propertyTypeCache)) {
      $propertyTypes = [];
      $reflectionClass = new ReflectionClass($targetType);

      foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        $propertyTypes[$property->getName()] = $property->getType();
      }

      self::$propertyTypeCache[$targetType] = $propertyTypes;
    }

    return self::$propertyTypeCache[$targetType];
  }

  protected static function getValidPropertyValue(
    ?ReflectionType $propertyReflectionType,
    int|string $key,
    mixed $value
  ): mixed
  {
    if ($propertyReflectionType === null) {
      return $value;
    }

    if ($value === null) {
      if ($propertyReflectionType instanceof ReflectionIntersectionType || !$propertyReflectionType->allowsNull()) {
        throw new HttpException("Cannot assign null to {$key}.", HttpStatus::BadRequest());
      }

      return null;
    }

    if ($propertyReflectionType instanceof ReflectionUnionType) {
      return self::getValidUnionPropertyValue($propertyReflectionType, $key, $value);
    }

    if ($propertyReflectionType instanceof ReflectionIntersectionType) {
      return $value;
    }

    if (!$propertyReflectionType instanceof ReflectionNamedType) {
      return $value;
    }

    return self::getValidNamedPropertyValue($propertyReflectionType, $key, $value);
  }

  protected static function getValidUnionPropertyValue(
    ReflectionUnionType $propertyReflectionType,
    int|string $key,
    mixed $value
  ): mixed
  {
    $lastException = null;

    foreach ($propertyReflectionType->getTypes() as $type) {
      if (!$type instanceof ReflectionNamedType || $type->getName() === 'null') {
        continue;
      }

      try {
        return self::getValidNamedPropertyValue($type, $key, $value);
      } catch (HttpException $exception) {
        $lastException = $exception;
      }
    }

    throw $lastException ?? new HttpException("Cannot assign value to {$key}.", HttpStatus::BadRequest());
  }

  protected static function getValidNamedPropertyValue(
    ReflectionNamedType $propertyReflectionType,
    int|string $key,
    mixed $value
  ): mixed
  {
    $typeName = $propertyReflectionType->getName();

    if ($typeName === 'mixed') {
      return $value;
    }

    if ($typeName === 'array') {
      if ($value instanceof stdClass || is_array($value)) {
        return self::normalizeArrayValue($value);
      }

      throw new HttpException("Cannot assign value to array property {$key}.", HttpStatus::BadRequest());
    }

    if ($typeName === 'object') {
      if (is_object($value)) {
        return $value;
      }

      throw new HttpException("Cannot assign value to object property {$key}.", HttpStatus::BadRequest());
    }

    if ($typeName === stdClass::class) {
      if ($value instanceof stdClass) {
        return $value;
      }

      if (is_array($value)) {
        return (object)self::normalizeArrayValue($value);
      }

      throw new HttpException("Cannot assign value to object property {$key}.", HttpStatus::BadRequest());
    }

    if (in_array($typeName, ['string', 'int', 'float', 'bool'], true)) {
      if (is_scalar($value)) {
        return $value;
      }

      throw new HttpException("Cannot assign value to {$typeName} property {$key}.", HttpStatus::BadRequest());
    }

    if ($typeName === DateTime::class) {
      if ($value instanceof DateTime) {
        return $value;
      }

      if (!is_string($value)) {
        throw new HttpException("Cannot assign value to DateTime property {$key}.", HttpStatus::BadRequest());
      }

      try {
        return new DateTime($value);
      } catch (Throwable $e) {
        throw new HttpException($e->getMessage(), HttpStatus::BadRequest(), previous: $e);
      }
    }

    if (enum_exists($typeName)) {
      if ($value instanceof $typeName) {
        return $value;
      }

      $reflectionEnum = new ReflectionEnum($typeName);

      if (!$reflectionEnum->isBacked() || !is_subclass_of($typeName, BackedEnum::class)) {
        throw new HttpException("Cannot assign value to enum property {$key}.", HttpStatus::BadRequest());
      }

      $backingType = $reflectionEnum->getBackingType()->getName();
      $valueType = match (gettype($value)) {
        'integer' => 'int',
        'boolean' => 'bool',
        'double' => 'float',
        default => gettype($value),
      };

      if ($backingType !== $valueType) {
        throw new HttpException("Cannot assign value to enum property {$key}.", HttpStatus::BadRequest());
      }

      /** @var class-string<BackedEnum> $typeName */
      $enumValue = $typeName::tryFrom($value);

      if ($enumValue === null) {
        throw new HttpException("Cannot assign value to enum property {$key}.", HttpStatus::BadRequest());
      }

      return $enumValue;
    }

    if (interface_exists($typeName)) {
      if ($value instanceof $typeName) {
        return $value;
      }

      throw new HttpException("Cannot assign value to {$key}.", HttpStatus::BadRequest());
    }

    if (class_exists($typeName)) {
      if ($value instanceof $typeName) {
        return $value;
      }

      if ($value instanceof stdClass) {
        return self::castObjectToUserType($value, $typeName);
      }

      if (is_array($value)) {
        return self::castObjectToUserType((object)$value, $typeName);
      }

      throw new HttpException("Cannot assign value to {$key}.", HttpStatus::BadRequest());
    }

    return $value;
  }

  /**
   * @param array<array-key, mixed>|stdClass $value
   * @return array<array-key, mixed>
   */
  protected static function normalizeArrayValue(array|stdClass $value): array
  {
    $array = $value instanceof stdClass ? get_object_vars($value) : $value;

    foreach ($array as $key => $item) {
      if ($item instanceof stdClass || is_array($item)) {
        $array[$key] = self::normalizeArrayValue($item);
      }
    }

    return $array;
  }
}
