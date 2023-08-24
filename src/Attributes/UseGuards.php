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

    $guardsList[] = match (true) {
      is_array($this->guard) => $this->getGuardsFromList($this->guard),
      $this->guard instanceof ICanActivate => $this->guard,
      is_string($this->guard) => $this->getGuardClassName($this->guard),
      default => throw new GuardException(message: "Invalid guard type")
    };

    $this->guards = $guardsList;
  }

  /**
   * Returns an array of guards objects from the given list of guards.
   *
   * @param ICanActivate[]|string[] $guardsList The list of guards.
   * @return ICanActivate[] Returns an array of guards.
   * @throws ContainerException
   * @throws GuardException
   * @throws ReflectionException
   */
  private function getGuardsFromList(array $guardsList): array
  {
    $guards = [];

    foreach ($guardsList as $guard)
    {
      if ($guard instanceof ICanActivate)
      {
        $guards[] = $guard;
      }
      else if (is_string($guard))
      {
        $guards[] = $this->getGuardClassName($guard);
      }
    }

    return $guards;
  }

  /**
   * Returns a guard instance from the given class name.
   *
   * @param string $guardClass The guard class name.
   * @return ICanActivate Returns a guard instance from the given class name.
   * @throws GuardException
   * @throws ReflectionException
   * @throws ContainerException
   */
  private function getGuardClassName(string $guardClass): ICanActivate
  {
    $reflectionClass = new ReflectionClass($guardClass);

    if (! $reflectionClass->isInstantiable() )
    {
      throw new GuardException(message: "$guardClass is not instantiable");
    }

    $guardConstructorArgs = [];
    $guardConstructorParameters = $reflectionClass->getConstructor()->getParameters();

    foreach ($guardConstructorParameters as $reflectionParameter)
    {
      $guardConstructorArgs[] = $this->injector->resolve($reflectionParameter->getType()->getName());
    }

    return $reflectionClass->newInstanceArgs($guardConstructorArgs);
  }
}