<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Exceptions\Http\HttpException;
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
   * @throws ReflectionException|HttpException
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
      throw new HttpException("Value passed to Validation pipe is not a valid class");
    }

    return $value;
  }
}