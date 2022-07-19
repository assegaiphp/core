<?php

namespace Assegai\Core;

use Assegai\Core\Enumerations\Scope;

final class ScopeOptions
{
  public function __construct(
    public readonly Scope $scope = Scope::DEFAULT,
    public readonly bool $durable = true
  )
  {
  }
}