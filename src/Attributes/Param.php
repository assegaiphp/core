<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Http\Request;
use Assegai\Core\Interfaces\IPipeTransform;
use Attribute;
use stdClass;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
  public readonly string|stdClass $value;

  /**
   * @param string|null $key
   * @param array|IPipeTransform|null $pipes
   */
  public function __construct(
    public readonly ?string $key = null,
    public readonly null|array|IPipeTransform $pipes = null
  )
  {
    $request = Request::getInstance();
    $params = $request->getParams();
    $value = ( !empty($this->key) ) ? ($params[$this->key] ?? $params) : json_decode(json_encode($params));

    if ($this->pipes)
    {
      if (is_array($this->pipes))
      {
        foreach ($this->pipes as $pipe)
        {
          if ($pipe instanceof IPipeTransform)
          {
            $value = $pipe->transform($value);
          }
        }
      }
      else
      {
        $value = $this->pipes->transform($value);
      }
    }

    $this->value = $value;
  }
}