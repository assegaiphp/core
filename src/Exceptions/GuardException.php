<?php

namespace Assegai\Core\Exceptions;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;

class GuardException extends HttpException
{
  public function __construct(string $message = "", ?HttpStatusCode $status = null)
  {
    if (!$status)
    {
      $status = HttpStatus::BadRequest();
    }

    parent::__construct(message: $message, status: $status);
  }
}