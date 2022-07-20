<?php

namespace Assegai\Core\Exceptions\Http;

use Assegai\Core\Http\HttpStatus;
use stdClass;

class UnauthorizedException extends HttpException
{
  public function __construct(array|string|stdClass $message = '')
  {
    parent::__construct($message);
    $this->setStatus(HttpStatus::Unauthorized());
  }
}