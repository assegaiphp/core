<?php

namespace Assegai\Core\Util;

/**
 * Contains methods for common tasks when working with arrays.
 */
final class ArrayHelper
{
  private function __construct() {}

  /**
   * @param array $array
   * @return bool
   */
  public static function isAssociative(array $array): bool
  {
    if (empty($array))
    {
      return false;
    }

    $keys = array_keys($array);
    foreach ($keys as $key)
    {
      if (!is_int($key))
      {
        return true;
      }
    }

    return false;
  }

  /**
   * @param array $array
   * @return bool
   */
  public static function isNotAssociative(array $array): bool
  {
    return !self::isAssociative(array: $array);
  }
}