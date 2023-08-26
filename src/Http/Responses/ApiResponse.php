<?php

namespace Assegai\Core\Http\Responses;

use Assegai\Core\Http\Requests\Request;
use JetBrains\PhpStorm\ArrayShape;

/**
 * ApiResponse class. This class is used to return data from a controller.
 * It is used by the framework to return data to the client.
 * @package Assegai\Core\Http\Responses
 */
class ApiResponse
{
  /**
   * @var Request The request object.
   */
  protected Request $request;

  /**
   * ApiResponse constructor.
   *
   * @param mixed $data The data to return.
   * @param int|null $total The total number of records.
   * @param Request|null $request The request object.
   */
  public function __construct(
    public readonly mixed $data,
    public readonly ?int $total = null,
    ?Request $request = null
  )
  {
    $this->request = $request ?? Request::getInstance();
  }

  /**
   * Returns the data as an array.
   *
   * @return array The data as an array.
   */
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

  /**
   * Returns the data as JSON.
   *
   * @return string The data as JSON.
   */
  public function toJSON(): string
  {
    return json_encode($this->toArray());
  }

  /**
   * Returns the data as an array.
   *
   * @return array The data as an array.
   */
  #[ArrayShape(['data' => "mixed", 'skip' => "int", 'limit' => "int", 'total' => "int"])]
  public function __serialize(): array
  {
    return $this->toArray();
  }

  /**
   * Returns the data as a string.
   *
   * @return string The data as a string.
   */
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

  /**
   * Returns the total number of records.
   *
   * @return int The total number of records.
   */
  private function getTotal(): int
  {
    if (!is_countable($this->data))
    {
      return empty($this->data) ? 0 : 1;
    }

    return $this->total ?? count($this->data);
  }
}