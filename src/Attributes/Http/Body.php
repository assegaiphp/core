<?php

namespace Assegai\Core\Attributes\Http;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Http\Requests\Request;
use Attribute;
use stdClass;

#[Injectable]
#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
  public string|array|stdClass $value;

  /**
   * @param string|null $key
   */
  public function __construct(public readonly ?string $key = null)
  {
    $request = Request::getInstance();
    $this->value = $request->getBody();

    if (!empty($this->key) )
    {
      $this->value = $this->value->$key;
    }
  }
}