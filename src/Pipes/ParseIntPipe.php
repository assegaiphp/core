<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Interfaces\IPipeTransform;
use stdClass;

class ParseIntPipe implements IPipeTransform
{

  /**
   * @param mixed $value
   * @param array|stdClass|null $metaData
   * @return int
   */
  public function transform(mixed $value, array|stdClass|null $metaData = null): int
  {
    return intval($value);
  }
}