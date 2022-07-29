<?php

namespace Assegai\Core\Http\Responses;

use Assegai\Core\Http\Requests\Request;
use JetBrains\PhpStorm\ArrayShape;

class ApiResponse
{
  protected Request $request;

  public function __construct(
    public readonly mixed $data,
    public readonly ?int $total = null,
    ?Request $request = null
  )
  {
    $this->request = $request ?? Request::getInstance();
  }

  #[ArrayShape(['data' => 'mixed', 'skip' => 'int', 'limit' => 'int', 'total' => 'int'])]
  public function toArray(): array
  {
    $data = $this->data;
    return [
      'total' => $this->getTotal(),
      'limit' => $this->request->getLimit(),
      'skip' => $this->request->getSkip(),
      'data' => $data,
    ];
  }

  public function toJSON(): string
  {
    return json_encode($this->toArray());
  }

  #[ArrayShape(['data' => "mixed", 'skip' => "int", 'limit' => "int", 'total' => "int"])]
  public function __serialize(): array
  {
    return $this->toArray();
  }

  public function __toString(): string
  {
    return match(gettype($this->data)) {
      'object' => method_exists($this->data, 'toJSON') ? $this->data->toJSON() : json_encode($this->data),
      'integer',
      'double',
      'boolean' => strval($this->data),
      'string' => $this->data,
      default => $this->toJSON()
    };
  }

  private function getTotal(): int
  {
    if (!is_countable($this->data))
    {
      return empty($this->data) ? 0 : 1;
    }

    return $this->total ?? count($this->data);
  }
}