<?php

namespace Assegai\Core\Util;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
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

    if (! class_exists($targetType) )
    {
      throw new EntryNotFoundException($targetType);
    }

    foreach ($object as $key => $value)
    {
      if (property_exists($instance, $key))
      {
        $instance->$key = $value;
      }
    }

    return $instance;
  }
}