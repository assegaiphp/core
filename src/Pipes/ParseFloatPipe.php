<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Exceptions\Http\BadRequestException;
use Assegai\Core\Http\Responses\Responders\Responder;
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
    if (!is_numeric($value))
    {
      Responder::getInstance()->respond(new BadRequestException("$value is not a numeric string"));
    }

    return floatval($value);
  }
}