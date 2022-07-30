<?php

namespace Assegai\Core\Exceptions;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatusCode;
use stdClass;

class FileException extends HttpException
{
  public function __construct(array|string|stdClass $message, ?HttpStatusCode $status = null)
  {
    parent::__construct($message, $status);
  }
}