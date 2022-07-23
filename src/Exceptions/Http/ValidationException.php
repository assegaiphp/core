<?php

namespace Assegai\Core\Exceptions\Http;

use stdClass;

class ValidationException extends BadRequestException
{
  public function __construct(string $expected = '')
  {
    parent::__construct(sprintf("Validation failed (%s is expected)", $expected));
  }
}