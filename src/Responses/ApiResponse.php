<?php

namespace Assegai\Core\Responses;

use Assegai\Core\Http\Request;
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
      'data' => $data,
      'skip' => $this->request->getSkip(),
      'limit' => $this->request->getLimit(),
      'total' => $this->getTotal(),
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
    return is_string($this->data) ? $this->data : $this->toJSON();
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