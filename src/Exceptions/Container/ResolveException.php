<?php

namespace Assegai\Core\Exceptions\Container;

use Throwable;

class ResolveException extends ContainerException
{
  public function __construct(string $id, string $message, ?Throwable $previous = null)
  {
    parent::__construct(message: "Resolve Error ($id): $message", previous: $previous);
  }
}
