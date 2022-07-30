<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\GuardException;
use Assegai\Core\Interfaces\ICanActivate;
use Attribute;
use ReflectionClass;
use ReflectionException;

/**
 * Specifies which Guards determine whether an endpoint can be activated.
 */
#[Attribute]
class UseGuards
{
  public readonly array $guards;

  /**
   * @param ICanActivate[]|string[]|ICanActivate|string $guard
   * @throws GuardException
   * @throws ReflectionException
   */
  public function __construct(protected readonly array|ICanActivate|string $guard)
  {
    $guardsList = [];

    if ($this->guard instanceof ICanActivate)
    {
      $guardsList[] = $this->guard;
    }
    else if (is_string($this->guard))
    {
      $reflectionClass = new ReflectionClass($this->guard);

      if (! $reflectionClass->isInstantiable() )
      {
        throw new GuardException(message: "$this->guard is not instantiable");
      }

      $guardsList[] = $reflectionClass->newInstance();
    }
    else
    {
      foreach ($this->guard as $item)
      {
        if ($item instanceof ICanActivate)
        {
          $guardsList[] = $item;
        }
        else if (is_string($item))
        {
          $reflectionClass = new ReflectionClass($item);

          if (! $reflectionClass->isInstantiable() )
          {
            throw new GuardException(message: "$item is not instantiable");
          }

          $guardsList[] = $reflectionClass->newInstance();
        }
      }
    }

    $this->guards = $guardsList;
  }
}