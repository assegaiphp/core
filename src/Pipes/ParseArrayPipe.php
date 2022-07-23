<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Exceptions\Http\BadRequestException;
use Assegai\Core\Interfaces\IPipeTransform;
use Assegai\Core\Responses\Responder;
use PHPUnit\Exception;
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
      try {
        $array = json_decode($value, true);
      }
      catch (Exception)
      {
        Responder::getInstance()->respond(new BadRequestException("Invalid JSON string"));
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