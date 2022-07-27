<?php

namespace Assegai\Core\Attributes\Http;

use Assegai\Core\Http\Requests\Query;
use Assegai\Core\Http\Requests\Request;
use Attribute;

/**
 * Binds the current client request `Query` object to the target parameter.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Queries
{
  /**
   * @var string|Query
   */
  public string|Query $value;

  /**
   * @param string|null $key
   */
  public function __construct(public readonly ?string $key = null)
  {
    $request = Request::getInstance();
    $this->value = $request->getQuery();

    if (!empty($this->key) )
    {
      $this->value = $this->value->$key;
    }
  }
}