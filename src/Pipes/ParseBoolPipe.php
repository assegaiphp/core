<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Interfaces\IPipeTransform;
use stdClass;

class ParseBoolPipe implements IPipeTransform
{

  /**
   * @param mixed $value
   * @param array|stdClass|null $metaData
   * @return bool
   */
  public function transform(mixed $value, array|stdClass|null $metaData = null): bool
  {
    return boolval($value);
  }
}