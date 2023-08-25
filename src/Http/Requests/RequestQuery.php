<?php

namespace Assegai\Core\Http\Requests;

use stdClass;

/**
 * Class RequestQuery represents the query string of a request.
 * @package Assegai\Core\Http\Requests
 */
class RequestQuery extends stdClass
{
  /**
   * @var string The raw query string.
   */
  public readonly string $raw;
  /**
   * @var array The query string as an array.
   */
  protected array $props;

  /**
   * RequestQuery constructor.
   */
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

  /**
   * Checks whether the given key exists in the query string.
   *
   * @param string $key The key.
   * @return bool Returns true if the key exists in the query string, false otherwise.
   */
  public function has(string $key): bool
  {
    return isset($this->props[$key]);
  }

  /**
   * Returns the value of the given key. If no key is given, returns the entire query string.
   *
   * @param string|null $key The key.
   * @param mixed $default The default value to return if the key is not found.
   * @return array|string Returns the value of the given key. If no key is given, returns the entire query string.
   */
  public function get(?string $key = null, mixed $default = ''): array|string
  {
    if (!$key)
    {
      return $this->props;
    }

    return $this->props[$key] ?? $default;
  }

  /**
   * Returns the query string as an array.
   *
   * @return array Returns the query string as an array.
   */
  public function toArray(): array
  {
    return $this->props;
  }
}