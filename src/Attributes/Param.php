<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Responder;
use Assegai\Core\Interfaces\IPipeTransform;
use Attribute;
use stdClass;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
  public readonly string|stdClass $value;

  /**
   * @param string|null $key
   * @param array|IPipeTransform|string|null $pipes
   */
  public function __construct(
    public readonly ?string $key = null,
    public readonly array|IPipeTransform|string|null $pipes = null
  )
  {
    $request = Request::getInstance();
    $params = $request->getParams();
    $value = ( !empty($this->key) ) ? ($params[$this->key] ?? $params) : json_decode(json_encode($params));

    if ($this->pipes)
    {
      if(is_string($value))
      {
        if (!is_subclass_of($this->pipes, IPipeTransform::class, true))
        {
          Responder::getInstance()->respond(new EntryNotFoundException($this->pipes));
        }
      }
      else if (is_array($this->pipes))
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