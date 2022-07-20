<?php

namespace Assegai\Core;

use Assegai\Core\Enumerations\Http\ContextType;

class AppConfig
{
  public function __construct(
    public readonly ContextType $type = ContextType::HTTP
  )
  {
  }
}