<?php

namespace Assegai\Core\Exceptions\Container;

use Assegai\Core\Exceptions\Http\HttpException;
use Throwable;

class ContainerException extends HttpException
{
  public function __construct(string $message = '', ?Throwable $previous = null)
  {
    parent::__construct(sprintf("Container exception: %s", $message), previous: $previous);
  }
}
