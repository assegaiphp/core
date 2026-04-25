<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Http\Requests\Request;
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
  public readonly string|stdClass $value;

  /**
   * @param string|null $key
   * @param array<int, IPipeTransform|string>|IPipeTransform|string|null $pipes
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
    $request = Request::current();
    $params = $request->getParams();
    $encodedParams = json_encode($params);
    $value = (!empty($this->key))
      ? ($params[$this->key] ?? $params)
      : (is_string($encodedParams) ? json_decode($encodedParams) : new stdClass());

    if ($this->pipes) {
      if (is_string($this->pipes)) {
        if (!is_subclass_of($this->pipes, IPipeTransform::class, true)) {
          throw new EntryNotFoundException($this->pipes);
        }

        /** @var IPipeTransform $pipe */
        $pipe = new $this->pipes;
        $value = $pipe->transform($value);
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
      is_string($value) => $value,
      default => new stdClass()
    };
  }
}
