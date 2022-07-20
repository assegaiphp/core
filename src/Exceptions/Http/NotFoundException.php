<?php

namespace Assegai\Core\Exceptions\Http;

use Assegai\Core\Http\HttpStatus;
use stdClass;

class NotFoundException extends HttpException
{
  public function __construct(string $path)
  {
    $uri = str_starts_with('/', $path) ? $path : "/$path";
    parent::__construct('Not Found: ' . $uri);
    $this->setStatus(HttpStatus::NotFound());
  }
}