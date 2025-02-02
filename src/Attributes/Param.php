<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Responders\Responder;
use Assegai\Core\Interfaces\IPipeTransform;
use Attribute;
use ReflectionException;
use stdClass;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * An attribute that binds a function parameter to a request parameter. If no key is specified, then the whole request
 * parameter object will be bound.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
  /**
   * @var string|stdClass|array|mixed|object
   */
  public readonly string|stdClass $value;

  /**
   * @param string|null $key
   * @param array|IPipeTransform|string|null $pipes
   * @throws RenderingException
   * @throws ReflectionException
   * @throws LoaderError
   * @throws RuntimeError
   * @throws SyntaxError
   */
  public function __construct(
    public readonly ?string $key = null,
    public readonly array|IPipeTransform|string|null $pipes = null
  )
  {
    $request = Request::getInstance();
    $params = $request->getParams();
    $value = ( !empty($this->key) ) ? ($params[$this->key] ?? $params) : json_decode(json_encode($params));

    if ($this->pipes) {
      if(is_string($value)) {
        if (!is_subclass_of($this->pipes, IPipeTransform::class, true)) {
          Responder::getInstance()->respond(new EntryNotFoundException($this->pipes));
        }
      } else if (is_array($this->pipes)) {
        foreach ($this->pipes as $pipe) {
          if ($pipe instanceof IPipeTransform) {
            $value = $pipe->transform($value);
          }
        }
      } else {
        $value = $this->pipes->transform($value);
      }
    }

    $this->value = match(true) {
      is_array($value) => (object)$value,
      is_bool($value),
      is_numeric($value) => (string)$value,
      default => $value
    };
  }
}