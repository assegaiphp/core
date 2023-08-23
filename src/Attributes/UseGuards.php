<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\GuardException;
use Assegai\Core\Injector;
use Assegai\Core\Interfaces\ICanActivate;
use Attribute;
use ReflectionClass;
use ReflectionException;

/**
 * An attribute that specifies which Guards to use when determining whether an endpoint can be activated.
 */
#[Attribute]
class UseGuards
{
  public readonly array $guards;
  protected Injector $injector;

  /**
   * @param ICanActivate[]|string[]|ICanActivate|string $guard
   * @throws GuardException
   * @throws ReflectionException
   * @throws ContainerException
   */
  public function __construct(protected readonly array|ICanActivate|string $guard)
  {
    $guardsList = [];
    $this->injector = Injector::getInstance();

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

      $guardConstructorArgs = [];
      $guardConstructorParameters = $reflectionClass->getConstructor()->getParameters();

      foreach ($guardConstructorParameters as $reflectionParameter)
      {
        $guardConstructorArgs[] = $this->injector->resolve($reflectionParameter->getType()->getName());
      }

      $guardsList[] = $reflectionClass->newInstanceArgs($guardConstructorArgs);
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