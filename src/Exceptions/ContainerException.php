<?php

namespace Assegai\Core\Exceptions;

use Assegai\Core\Responses\HttpStatus;
use Exception;

class ContainerException extends Exception
{
  public function __construct(string $message)
  {
    http_response_code(HttpStatus::InternalServerError()->code());
    parent::__construct(sprintf("Container exception: ", $message));
  }
}