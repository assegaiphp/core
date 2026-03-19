<?php

namespace Assegai\Core\Consumers;

use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\GuardException;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Injector;
use Assegai\Core\Interfaces\ICanActivate;
use ReflectionClass;
use ReflectionException;
use ReflectionUnionType;

/**
 * Consumes guards.
 */
final class GuardsConsumer
{
  /**
   * The singleton instance.
   *
   * @var ?self
   */
  private static ?self $instance = null;

  /**
   * Constructs a new GuardsConsumer instance.
   */
  private final function __construct()
  {
  }

  /**
   * Returns the singleton instance of the GuardsConsumer.
   *
   * @return static The singleton instance of the GuardsConsumer.
   */
  public static function getInstance(): self
  {
    if (! self::$instance)
    {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Determines if all guards can activate.
   *
   * @param array<int, ICanActivate|string|array> $guards The guards.
   * @param ExecutionContext $context The execution context.
   * @return bool True if all guards can activate, false otherwise.
   * @throws ContainerException
   * @throws GuardException
   * @throws ReflectionException
   */
  public function canActivate(array $guards, ExecutionContext $context): bool
  {
    foreach ($this->resolveGuards($guards) as $guard) {
      if (!$guard->canActivate(context: $context)) {
        return false;
      }
    }

    return true;
  }

  /**
   * @param array<int, ICanActivate|string|array> $guards
   * @return array<int, ICanActivate>
   * @throws ContainerException
   * @throws GuardException
   * @throws ReflectionException
   */
  private function resolveGuards(array $guards): array
  {
    $resolvedGuards = [];

    foreach ($guards as $guard) {
      if (is_array($guard)) {
        $resolvedGuards = [...$resolvedGuards, ...$this->resolveGuards($guard)];
        continue;
      }

      if ($guard instanceof ICanActivate) {
        $resolvedGuards[] = $guard;
        continue;
      }

      if (is_string($guard)) {
        $resolvedGuards[] = $this->resolveGuard($guard);
        continue;
      }

      throw new GuardException(message: 'Invalid guard type');
    }

    return $resolvedGuards;
  }

  /**
   * @param class-string<ICanActivate> $guardClass
   * @return ICanActivate
   * @throws ContainerException
   * @throws GuardException
   * @throws ReflectionException
   */
  private function resolveGuard(string $guardClass): ICanActivate
  {
    $injector = Injector::getInstance();

    try {
      $resolved = $injector->resolve($guardClass);

      if ($resolved instanceof ICanActivate) {
        return $resolved;
      }
    } catch (ContainerException) {
      // Fall back to direct construction for simple guard classes.
    }

    $reflectionClass = new ReflectionClass($guardClass);

    if (!$reflectionClass->isInstantiable()) {
      throw new GuardException(message: "$guardClass is not instantiable");
    }

    $constructor = $reflectionClass->getConstructor();

    if (!$constructor || !$constructor->getParameters()) {
      $instance = $reflectionClass->newInstance();

      if ($instance instanceof ICanActivate) {
        return $instance;
      }

      throw new GuardException(message: "$guardClass must implement ICanActivate");
    }

    $dependencies = [];

    foreach ($constructor->getParameters() as $parameter) {
      $type = $parameter->getType();

      if (!$type || $type instanceof ReflectionUnionType || $type->isBuiltin()) {
        $dependencies[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        continue;
      }

      $dependencies[] = $injector->resolve($type->getName());
    }

    $instance = $reflectionClass->newInstanceArgs($dependencies);

    if (!$instance instanceof ICanActivate) {
      throw new GuardException(message: "$guardClass must implement ICanActivate");
    }

    return $instance;
  }
}
