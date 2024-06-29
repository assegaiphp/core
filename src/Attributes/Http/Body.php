<?php

namespace Assegai\Core\Attributes\Http;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Responder;
use Assegai\Core\Interfaces\IPipeTransform;
use Attribute;
use stdClass;

/**
 * Binds the current request body to the target parameter.
 *
 * @package Assegai\Core\Attributes\Http
 */
#[Injectable]
#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
  /**
   * @var string|array|stdClass|mixed|null $value
   */
  public string|array|stdClass $value;

  /**
   * Body constructor.
   *
   * @param string|null $key The key of the body
   * @param array|IPipeTransform|string|null $pipes
   */
  public function __construct(
    public readonly ?string $key = null,
    public readonly array|IPipeTransform|string|null $pipes = null
  )
  {
    $request = Request::getInstance();
    $value = $request->getBody();

    if ($this->pipes)
    {
      if(is_string($this->pipes))
      {
        if (!is_subclass_of($this->pipes, IPipeTransform::class, true))
        {
          Responder::getInstance()->respond(new EntryNotFoundException($this->pipes));
        }

        /** @var IPipeTransform $pipe */
        $pipe = new $this->pipes;
        $value = $pipe->transform($value);
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

    if (!empty($this->key) && isset($this->value->$key))
    {
      $this->value = $this->value->$key;
    }
  }
}