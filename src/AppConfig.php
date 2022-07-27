<?php

namespace Assegai\Core;

use Assegai\Core\Enumerations\Http\ContextType;
use Dotenv\Dotenv;

class AppConfig
{
  public function __construct(
    public readonly ContextType $type = ContextType::HTTP
  )
  {
    $workingDirectory = trim(exec("pwd"));
    $dotenv = Dotenv::createImmutable($workingDirectory);
    $dotenv->safeLoad();
  }
}