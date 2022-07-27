<?php

namespace Assegai\Core\Routing;

use Assegai\Core\Enumerations\Http\RequestMethod;

class Route
{
  public function __construct(
    public readonly string $path,
    public readonly RequestMethod $method = RequestMethod::GET
  )
  {
  }
}