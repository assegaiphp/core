<?php

namespace Assegai\Core\Http\Requests;

use stdClass;

class RequestQuery extends stdClass
{
  public readonly string $raw;
  protected array $props;

  public function __construct()
  {
    $this->raw = $_SERVER['QUERY_STRING'] ?? '';

    $this->props = [];
    parse_str($this->raw, $this->props);

    foreach ($this->props as $key => $value)
    {
      $this->$key = $value;
    }
  }

  public function getProps(?string $key = null): array|string
  {
    return $this->props[$key] ?? $this->props;
  }
}