<?php

namespace Assegai\Core\Exceptions;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use stdClass;

class RenderingException extends HttpException
{
  public function __construct(string $message)
  {
    parent::__construct($message, HttpStatus::InternalServerError());
  }
}