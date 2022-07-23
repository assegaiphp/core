<?php

namespace Assegai\Core;

use Assegai\Core\Attributes\Body;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\Queries;
use Assegai\Core\Attributes\Req;
use Assegai\Core\Attributes\Res;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Container\ResolveException;
use Assegai\Core\Http\Request;
use Assegai\Core\Interfaces\IContainer;
use Assegai\Core\Interfaces\IEntryNotFoundException;
use Assegai\Core\Interfaces\ITokenStoreOwner;
use Assegai\Core\Responses\Response;
use Assegai\Core\Util\Types;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

final class Injector implements ITokenStoreOwner, IContainer
{
  private array $store = [];

  private final function __construct()
  {
  }

  public static function getInstance(): Injector
  {
    return new Injector();
  }

  /**
   * @throws ContainerException|ReflectionException
   */
  public function resolve(string $id): mixed
  {
    # 1. Inspect the class that we are trying to get from the container
    try
    {
      $reflectionClass = new ReflectionClass($id);

      if($this->isNotInjectable($reflectionClass))
      {
        throw new ContainerException("$id is not injectable");
      }
    }
    catch (ReflectionException)
    {
      throw new ContainerException("$id is not a valid ID");
    }

    if (! $reflectionClass->isInstantiable() )
    {
      if ($reflectionClass->hasMethod('getInstance'))
      {
        /** @noinspection PhpUndefinedMethodInspection */
        return $id::getInstance();
      }

      if ($reflectionClass->hasMethod('newInstance'))
      {
        /** @noinspection PhpUndefinedMethodInspection */
        return $id::newInstance();
      }

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

  /**
   * @param string $entryId
   * @return bool
   */
  public function has(string $entryId): bool
  {
    return isset($this->store[$entryId]);
  }

  /**
   * @param string $entryId
   * @return mixed
   */
  public function get(string $entryId): mixed
  {
    return $this->store[$entryId] ?? null;
  }

  /**
   * @param string $entryId
   * @param mixed $token
   * @return int
   */
  public function add(string $entryId, mixed $token): int
  {
    $this->store[$entryId] = $token;

    return count($this->store);
  }

  /**
   * @param $tokenId
   * @return int|false
   * @throws EntryNotFoundException
   */
  public function remove($tokenId): int|false
  {
    try
    {
      if (!$this->has($tokenId))
      {
        return false;
      }
    }
    catch (IEntryNotFoundException $e)
    {
      throw new EntryNotFoundException($e->getMessage());
    }

    unset($this->store[$tokenId]);

    return count($this->store);
  }

  public function isInjectable(ReflectionClass $reflectionClass): bool
  {
    $lastLoadedAttributes = $reflectionClass->getAttributes(Injectable::class);
    return !empty($lastLoadedAttributes);
  }

  public function isNotInjectable(ReflectionClass $reflectionClass): bool
  {
    return !$this->isInjectable($reflectionClass);
  }

  /**
   * @param string $id
   * @param ReflectionParameter[] $parameters
   * @return array
   * @throws ContainerException
   * @throws ResolveException
   */
  private function resolveDependencies(string $id, array $parameters): array
  {
    return array_map(function(ReflectionParameter $param) use ($id) {
      $paramName = $param->getName();
      $paramType = $param->getType();
      $resolveErrorPrefix = "Failed to resolve type $paramName";

      if (! $paramType )
      {
        throw new ResolveException(id: $id, message: "$resolveErrorPrefix — Illegal type — Undefined");
      }

      if ($paramType instanceof ReflectionUnionType)
      {
        throw new ResolveException(id: $id, message: "$resolveErrorPrefix — Illegal type — Union");
      }

      if ($paramType instanceof ReflectionNamedType && ! $paramType->isBuiltin())
      {
        if (enum_exists($paramType->getName()))
        {
          try
          {
            $reflectionEnum = new ReflectionEnum($paramType->getName());
            return $reflectionEnum->getCases()[0]->getValue();
          }
          catch (ReflectionException)
          {
            throw new ContainerException(sprintf("Enum exception %s(%s)", __METHOD__, __LINE__));
          }
        }

        if (is_array($paramType->getName()))
        {
          return $param->allowsNull() ? null : [];
        }

        # TODO: Check if param has Injectable class or attribute

        # TODO: Check if type has InjectRepository attribute
//        $repositoryAttributes = $param->getAttributes(InjectRepository::class);

//        foreach ( $repositoryAttributes as $reflectionRepoAttr )
//        {
//          $repoAttr = $reflectionRepoAttr->getArguments();
//        }

        return $this->get($paramType->getName());
      }

      return null;
    }, $parameters);
  }

  /**
   * @param ReflectionParameter $param
   * @param Request $request
   * @return mixed
   * @throws EntryNotFoundException
   */
  public function resolveBuiltIn(ReflectionParameter $param, Request $request): mixed
  {
    $paramTypeName = $param->getType()->getName();
    $paramAttributes = $param->getAttributes();

    foreach ($paramAttributes as $paramAttribute)
    {
      $paramAttributeArgs = $paramAttribute->getArguments();

      switch ($paramAttribute->getName())
      {
        case Param::class:
          if (empty($paramAttributeArgs))
          {
            return ($param->getType()->getName() === 'string')
              ? json_encode($request->getParams())
              : (object)$request->getParams();
          }
          return $request->getParams()[$param->getPosition()] ?? null;

        case Queries::class:
          if (empty($paramAttributeArgs))
          {
            return ($param->getType()->getName() === 'string')
              ? json_encode($request->getQuery())
              : (object)$request->getQuery();
          }
          return $request->getQuery()[$param->getPosition()] ?? null;

        case Body::class:
          $body = null;
          if (empty($paramAttributeArgs))
          {
            $body = ($param->getType()->getName() === 'string')
              ? json_encode($request->getBody())
              : $request->getBody();
          }
          else
          {
            $key = $param->getName();
            $body = $request->getBody()->$key ?? null;
          }
          return Types::castObjectToUserType($body, $paramTypeName);

        case Req::class:
          return $request;

        case Res::class:
          return Response::getInstance();
      }
    }

    return null;
  }
}