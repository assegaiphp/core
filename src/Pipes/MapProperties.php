<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Interfaces\IPipeTransform;
use stdClass;

/**
 * Class MapProperties maps properties from one object to another.
 * @package Assegai\Core\Pipes
 */
class MapProperties implements IPipeTransform
{
  /**
   * MapProperties constructor.
   * @param array $map
   */
  public function __construct(public array $map)
  {

  }
  /**
   * @inheritDoc
   */
  public function transform(mixed $value, array|stdClass|null $metaData = null): mixed
  {
    if (is_object($value))
    {
      foreach ($this->map as $source => $target)
      {
        if (property_exists($value, $source))
        {
          $value->$target = $value->$source;
          unset($value->$source);
        }
      }
    }

    return $value;
  }
}