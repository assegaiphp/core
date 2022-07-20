<?php

namespace Assegai\Core\Exceptions\Container;

use Assegai\Core\Exceptions\HttpException;

class ContainerException extends HttpException
{
  public function __construct(string $message = '')
  {
    parent::__construct(sprintf("Container exception: %s", $message));
  }
}