<?php

namespace Assegai\Core\Http;

use stdClass;

class Query extends stdClass
{
  public readonly string $raw;
  protected array $props;

  public function __construct()
  {
    $this->raw = $_SERVER['QUERY_STRING'];

    $this->props = [];
    parse_str($this->raw, $this->props);

    foreach ($this->props as $key => $value)
    {
      $this->$key = $value;
    }
  }

  public function getProps(): array
  {
    return $this->props;
  }
}