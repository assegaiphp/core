<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Exceptions\Http\BadRequestException;
use Assegai\Core\Http\Responses\Responder;
use Assegai\Core\Interfaces\IPipeTransform;
use Assegai\Validation\Validator;
use ReflectionException;
use stdClass;

/**
 * Provides a convenient approach to enforce validation rules for all incoming client payloads, where the specific
 * rules are declared with simple annotations in local class/DTO declarations in each module.
 */
class ValidationPipe implements IPipeTransform
{

  /**
   * @inheritDoc
   * @throws ReflectionException
   */
  public function transform(mixed $value, array|stdClass|null $metaData = null): mixed
  {
    if (!is_object($value))
    {
      return $value;
    }

    $isValidClass = Validator::validateClass($value);

    if (!$isValidClass)
    {
      Responder::getInstance()->respond(new BadRequestException("$value is not a numeric string"));
    }

    return $value;
  }
}