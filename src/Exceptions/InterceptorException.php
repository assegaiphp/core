<?php

namespace Assegai\Core\Exceptions;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\HttpStatusCode;
use stdClass;

class InterceptorException extends HttpException
{
  public function __construct(
    bool $isRequestError,
    array|string|stdClass $message = '',
    ?HttpStatusCode $status = null)
  {
    if (!$status)
    {
      $status = $isRequestError ? HttpStatus::BadRequest() : HttpStatus::InternalServerError();
    }

    if (empty($message))
    {
      $message = ($isRequestError ? 'Client request' : 'Server response') . ' interception error';
    }

    parent::__construct($message, $status);
  }
}