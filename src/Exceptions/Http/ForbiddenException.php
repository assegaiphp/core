<?php

namespace Assegai\Core\Exceptions\Http;

use Assegai\Core\Http\HttpStatus;
use stdClass;

class ForbiddenException extends HttpException
{
  public function __construct(array|string|stdClass $message = 'Forbidden resource')
  {
    parent::__construct($message);
    $this->setStatus(HttpStatus::Forbidden());
  }
}