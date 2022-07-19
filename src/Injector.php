<?php

namespace Assegai\Core;

use Assegai\Core\Exceptions\ContainerException;
use Assegai\Core\Interfaces\ITokenStoreOwner;
use ReflectionClass;

final class Injector implements ITokenStoreOwner
{
  private array $store = [];

  private final function __construct()
  {
  }

  public static function newInstance(): Injector
  {
    return new Injector();
  }

  public function resolve(string $id): mixed
  {
    # 1. Inspect the class that we are trying to get from the container
    $reflectionClass = new ReflectionClass($id);

    if (! $reflectionClass->isInstantiable() )
    {
      throw new ContainerException("Class '$id' is not instantiable");
    }

    # 2. Inspect the constructor of the class
    $constructor = $reflectionClass->getConstructor();

    if (! $constructor )
    {
      return new $id;
    }

    # 3. Inspect the constructor parameters (dependencies)
    $parameters = $constructor->getParameters();

    if (! $parameters )
    {
      return new $id;
    }

    # 4. If the constructor parameter is a class try to resolve it using the container
    $dependencies = $this->resolveDependencies(id: $id, parameters: $parameters);

    return $reflectionClass->newInstanceArgs($dependencies);
  }

  public function has(string $tokenId): bool
  {
    return isset($this->store[$tokenId]);    
  }

  public function get(string $tokenId): mixed
  {
    return $this->store[$tokenId] ?? null;
  }

  public function add(string $tokenId, mixed $token): int
  {
    $this->store[$tokenId] = $token;

    return count($this->store);
  }

  public function remove($tokenId): int|false
  {
    if (!$this->has($tokenId))
    {
      return false;
    }

    unset($this->store[$tokenId]);

    return count($this->store);
  }
}