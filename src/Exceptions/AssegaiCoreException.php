<?php

namespace Assegai\Core\Exceptions;

use Exception;
use Throwable;

class AssegaiCoreException extends Exception
{
  public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
  {
    parent::__construct("Assegai Core Exception: $message", $code, $previous);
  }
}