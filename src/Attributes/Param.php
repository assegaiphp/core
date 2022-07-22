<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Http\Request;
use Attribute;
use stdClass;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
  public readonly string|stdClass $value;

  public function __construct(public readonly ?string $key = null)
  {
    $request = Request::getInstance();
    $params = $request->getParams();

    $this->value = ( !empty($this->key) ) ? ($params[$this->key] ?? $params) : json_decode(json_encode($params));
  }
}