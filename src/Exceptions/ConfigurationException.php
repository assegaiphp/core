<?php

namespace Assegai\Core\Exceptions;

class ConfigurationException extends AssegaiCoreException
{
  public function __construct(string $message)
  {
    parent::__construct($message);
  }
}