<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Http\Query;
use Assegai\Core\Http\Request;
use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Queries
{
  public string|Query $value;

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