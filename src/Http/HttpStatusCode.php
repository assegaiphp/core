<?php

namespace Assegai\Core\Http;


class HttpStatusCode
{
  public function __construct(
    public readonly int    $code,
    public readonly string $name,
    public readonly string $description,
  ) { }

  public function __toString(): string
  {
    return "$this->code - $this->name";
  }
}