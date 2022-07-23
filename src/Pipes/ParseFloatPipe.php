<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Exceptions\Http\BadRequestException;
use Assegai\Core\Interfaces\IPipeTransform;
use Assegai\Core\Responses\Responder;
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
    if (!is_numeric($value))
    {
      Responder::getInstance()->respond(new BadRequestException("$value is not a numeric string"));
    }

    return floatval($value);
  }
}