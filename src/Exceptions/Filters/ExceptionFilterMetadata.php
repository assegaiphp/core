<?php

namespace Assegai\Core\Exceptions\Filters;

use Assegai\Attributes\OnException as PackageOnException;
use Assegai\Core\Attributes\OnException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Reads and normalizes exception filter metadata from framework attributes.
 */
final class ExceptionFilterMetadata
{
  private function __construct()
  {
  }

  /**
   * @param ReflectionClass<object>|ReflectionMethod $reflector
   * @return array<int, class-string<Throwable>>
   */
  public static function exceptionTypes(ReflectionClass|ReflectionMethod $reflector): array
  {
    $types = [];

    foreach ($reflector->getAttributes(OnException::class) as $attribute) {
      $instance = $attribute->newInstance();
      $types = [...$types, ...self::normalize($instance->exception)];
    }

    foreach ($reflector->getAttributes(PackageOnException::class) as $attribute) {
      $instance = $attribute->newInstance();
      $types = [...$types, ...self::normalize($instance->filterClassNames)];
    }

    return array_values(array_unique($types));
  }

  /**
   * @param string|Throwable|array<int, string|Throwable> $types
   * @return array<int, class-string<Throwable>>
   */
  public static function normalize(string|Throwable|array $types): array
  {
    $normalized = [];

    foreach (is_array($types) ? $types : [$types] as $type) {
      if ($type instanceof Throwable) {
        $type = $type::class;
      }

      if (!is_string($type) || (!is_a($type, Throwable::class, true) && $type !== Throwable::class)) {
        throw new InvalidArgumentException('Exception filter types must implement Throwable.');
      }

      $normalized[] = $type;
    }

    return array_values(array_unique($normalized));
  }
}
