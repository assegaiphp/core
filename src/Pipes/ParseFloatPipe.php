<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Interfaces\IPipeTransform;
use stdClass;

class ParseFloatPipe implements IPipeTransform
{

  /**
   * @param mixed $value
   * @param array|stdClass|null $metaData
   * @return float
   */
  public function transform(mixed $value, array|stdClass|null $metaData = null): float
  {
    return floatval($value);
  }
}