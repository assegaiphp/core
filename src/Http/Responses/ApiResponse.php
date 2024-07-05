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
  protected bool $isResultObject = false;

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
    $this->isResultObject = is_object($data) && method_exists($data, 'getData');
  }

  /**
   * Returns the data.
   *
   * @return mixed The data.
   */
  public function getData(): mixed
  {
    return $this->isResultObject ? $this->data->getData() : $this->data;
  }

  /**
   * Returns the data as an array.
   *
   * @return array{data: mixed, skip: int, limit: int, total: int} The data as an array.
   */
  public function toArray(): array
  {
    $data = $this->getData();

    if ($this->getTotal() === 1 && is_array($data))
    {
      return $data;
    }

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
    if ($this->getTotal() === 1 && is_array($this->getData()))
    {
      return json_encode($this->getData()[0]);
    }

    return json_encode($this->toArray());
  }

  /**
   * Returns the data as an array.
   *
   * @return array{data: mixed, skip: int, limit: int, total: int} The data as an array.
   */
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
      'object' => match(true) {
        method_exists($this->data, 'toJSON') => $this->data->toJSON(),
        $this->isResultObject => $this->toJSON(),
        default => json_encode($this->data)
      },
      'integer',
      'double',
      'boolean' => strval($this->data),
      'string' => $this->data,
      'array' => $this->getTotal() === 1 ? json_encode($this->data) :  $this->toJSON(),
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
    if ($this->isResultObject)
    {
      return $this->data->getTotal();
    }

    if (!is_countable($this->data))
    {
      return empty($this->data) ? 0 : 1;
    }

    return $this->total ?? count($this->data);
  }
}