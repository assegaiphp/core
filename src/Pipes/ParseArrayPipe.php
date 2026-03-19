<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Exceptions\Http\BadRequestException;
use Assegai\Core\Interfaces\IPipeTransform;
use stdClass;

class ParseArrayPipe implements IPipeTransform
{

  /**
   * @param mixed $value
   * @param array|stdClass|null $metaData
   * @return array
   */
  public function transform(mixed $value, array|stdClass|null $metaData = null): array
  {
    $array = [];

    if (is_string($value))
    {
      $array = json_decode($value, true);

      if (JSON_ERROR_NONE !== json_last_error()) {
        throw new BadRequestException("Invalid JSON string");
      }
    }
    else if (is_iterable($value))
    {
      foreach ($value as $key => $val)
      {
        $array[$key] = $val;
      }
    }

    return $array;
  }
}
