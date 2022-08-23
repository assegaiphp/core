<?php

namespace Assegai\Core\Exceptions;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatus;

class RenderingException extends HttpException
{
  public function __construct(string $message)
  {
    parent::__construct($message, HttpStatus::InternalServerError());
  }
}