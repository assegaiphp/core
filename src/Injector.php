<?php /** @noinspection ALL */

/** @noinspection ALL */

namespace Assegai\Core;

use Assegai\Attributes\Injectable;
use Assegai\Core\Attributes\Component;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Query;
use Assegai\Core\Attributes\Injectable as CoreInjectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\Req;
use Assegai\Core\Attributes\Res;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Container\ResolveException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\IContainer;
use Assegai\Core\Interfaces\IEntryNotFoundException;
use Assegai\Core\Interfaces\ITokenStoreOwner;
use Assegai\Core\Util\TypeManager;
use Codeception\Attribute\Skip;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Class Injector. The injector is responsible for resolving dependencies.
 */
final class Injector implements ITokenStoreOwner, IContainer
{
  /**
   * @var array<string, mixed> The store of dependencies.
   */
  protected array $store = [];

  /**
   * @var Injector|null The Injector instance.
   */
  protected static ?Injector $instance = null;

  /**
   * Constructs a new Injector instance.
   */
  private final function __construct()
  {
  }

  /**
   * Returns a singleton instance of the Injector.
   *
   * @return Injector The Injector instance.
   */
  public static function getInstance(): Injector
  {
    if (is_null(self::$instance)) {
      self::$instance = new Injector();
    }

    return self::$instance;
  }

  /**
   * Resolves a dependency.
   *
   * @param string $id The dependency ID.
   * @param \ReflectionAttribute[] $attributeReflections A list of reflection attributes.
   * @return mixed The resolved dependency.
   * @throws ContainerException|ReflectionException
   */
  public function resolve(string $id, array $attributeReflections = []): mixed
  {
    # 1. Inspect the class that we are trying to get from the container
    try {
      $reflectionClass = new ReflectionClass($id);
      $attributeReflections = $reflectionClass->getAttributes();

      if($this->isNotInjectable($reflectionClass)) {
        if ($this->isModule($reflectionClass)) {
          return $this->resolveModule($reflectionClass);
        }

        if ($this->isComponent($reflectionClass)) {
          return $this->resolveComponent($reflectionClass);
        }

        throw new ContainerException("$id is not injectable");
      }
    } catch (ReflectionException) {
      throw new ContainerException("$id is not a valid ID");
    }

    if (! $reflectionClass->isInstantiable() ) {
      if ($reflectionClass->hasMethod('getInstance')) {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->bindHandlerAttributes($id::getInstance(), $attributeReflections);
      }

      if ($reflectionClass->hasMethod('newInstance')) {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->bindHandlerAttributes($id::newInstance(), $attributeReflections);
      }

      throw new ContainerException("Class '$id' is not instantiable");
    }

    # 2. Inspect the constructor of the class
    $constructor = $reflectionClass->getConstructor();

    if (! $constructor ) {
      return $this->bindHandlerAttributes(new $id, $attributeReflections);
    }

    # 3. Inspect the constructor parameters (dependencies)
    $parameters = $constructor->getParameters();

    if (! $parameters ) {
      return $this->bindHandlerAttributes(new $id, $attributeReflections);
    }

    # 4. If the constructor parameter is a class try to resolve it using the container
    $dependencies = $this->resolveDependencies(id: $id, parameters: $parameters);

    return $this->bindHandlerAttributes($reflectionClass->newInstanceArgs($dependencies), $attributeReflections);
  }

  /**
   * Determines if the container has a dependency.
   *
   * @param string $entryId The dependency ID.
   * @return bool True if the container has the dependency, false otherwise.
   */
  public function has(string $entryId): bool
  {
    return isset($this->store[$entryId]);
  }

  /**
   * Returns a dependency from the container.
   *
   * @param string $entryId The dependency ID.
   * @return mixed The dependency if it exists, null otherwise.
   */
  public function get(string $entryId): mixed
  {
    return $this->store[$entryId] ?? null;
  }

  /**
   * Adds a dependency to the container.
   *
   * @param string $entryId The dependency ID.
   * @param mixed $token The dependency.
   * @return int The number of dependencies in the container.
   */
  public function add(string $entryId, mixed $token): int
  {
    $this->store[$entryId] = $token;

    return count($this->store);
  }

  /**
   * Removes a dependency from the container.
   *
   * @param $tokenId The dependency ID.
   * @return int|false The number of dependencies in the container, false if the dependency does not exist.
   * @throws EntryNotFoundException If the dependency does not exist.
   */
  public function remove($tokenId): int|false
  {
    try {
      if (!$this->has($tokenId)) {
        return false;
      }
    } catch (IEntryNotFoundException $e) {
      throw new EntryNotFoundException($e->getMessage());
    }

    unset($this->store[$tokenId]);

    return count($this->store);
  }

  /**
   * Determines if a class is injectable.
   *
   * @param ReflectionClass $reflectionClass The class to inspect.
   * @return bool True if the class is injectable, false otherwise.
   */
  public function isInjectable(ReflectionClass $reflectionClass): bool
  {
    $lastLoadedCoreAttributes = $reflectionClass->getAttributes(CoreInjectable::class);
    $lastLoadedAttributes = $reflectionClass->getAttributes(Injectable::class);

    return !empty($lastLoadedCoreAttributes) || !empty($lastLoadedAttributes);
  }

  /**
   * Determines if a class is not injectable.
   *
   * @param ReflectionClass $reflectionClass The class to inspect.
   * @return bool True if the class is not injectable, false otherwise.
   */
  public function isNotInjectable(ReflectionClass $reflectionClass): bool
  {
    return !$this->isInjectable($reflectionClass);
  }

  /**
   * Resolves dependencies.
   *
   * @param string $id The dependency ID.
   * @param ReflectionParameter[] $parameters The dependency parameters.
   * @return array The resolved dependencies.
   * @throws ContainerException If the dependency is not injectable.
   * @throws ResolveException If the dependency cannot be resolved.
   */
  private function resolveDependencies(string $id, array $parameters): array
  {
    return array_map(function(ReflectionParameter $param) use ($id) {
      $paramName = $param->getName();
      $paramType = $param->getType();
      $resolveErrorPrefix = "Failed to resolve type $paramName";

      if (! $paramType ) {
        throw new ResolveException(id: $id, message: "$resolveErrorPrefix — Illegal type — Undefined");
      }

      if ($paramType instanceof ReflectionUnionType) {
        throw new ResolveException(id: $id, message: "$resolveErrorPrefix — Illegal type — Union");
      }

      if ($paramType instanceof ReflectionNamedType && ! $paramType->isBuiltin()) {
        if (enum_exists($paramType->getName())) {
          try {
            $reflectionEnum = new ReflectionEnum($paramType->getName());
            return $reflectionEnum->getCases()[0]->getValue();
          } catch (ReflectionException) {
            throw new ContainerException(sprintf("Enum exception %s(%s)", __METHOD__, __LINE__));
          }
        }

        if (is_array($paramType->getName())) {
          return $param->allowsNull() ? null : [];
        }

        $repositoryAttributes = $param->getAttributes('Assegai\Orm\Attributes\InjectRepository');

        foreach ( $repositoryAttributes as $reflectionRepoAttr ) {
          $injectRepositoryInstance = $reflectionRepoAttr->newInstance();
          if (property_exists($injectRepositoryInstance, 'repository')) {
            return $injectRepositoryInstance->repository;
          }
        }

        # TODO: Check if param has Injectable class or attribute
        $repositoryAttributes = [...$param->getAttributes(CoreInjectable::class), ...$param->getAttributes(Injectable::class)];

        return $this->get($paramType->getName());
      }

      return null;
    }, $parameters);
  }

  /**
   * Resolves built-in parameters like scalar types, arrays, objects, etc.
   *
   * @param ReflectionParameter $param The parameter to resolve.
   * @param Request $request The request object.
   * @param bool $isUnionType True if the parameter is a union type, false otherwise.
   *
   * @return mixed The resolved parameter.
   * @throws EntryNotFoundException
   */
  public function resolveBuiltIn(ReflectionParameter $param, Request $request, bool $isUnionType = false): mixed
  {
    $paramType = $param->getType();
    $paramTypeName = match(true) {
      $isUnionType => ltrim(array_reduce($paramType->getTypes(), fn($carry, $type) => $carry . '|' . $type->getName(), '') ?? '', '|'),
      method_exists($param->getType(), 'getName') => $param->getType()->getName(),
      default => \stdClass::class
    };
    $paramAttributes = $param->getAttributes();

    foreach ($paramAttributes as $paramAttribute) {
      $paramAttributeArgs = $paramAttribute->getArguments();
      $paramAttributeInstance = $paramAttribute->newInstance();

      switch ($paramAttribute->getName()) {
        case Param::class:
          if (empty($paramAttributeArgs)) {
            return ($paramTypeName === 'string')
              ? json_encode($request->getParams())
              : (object)$request->getParams();
          }
          return $request->getParams()[$param->getPosition()] ??
            ($param->isOptional() ? $param->getDefaultValue() : null);

        case Query::class:;
          if (empty($paramAttributeArgs)) {
            return ($paramTypeName === 'string')
              ? json_encode($request->getQuery())
              : (object)$request->getQuery();
          }

          return $request->getQuery()->toArray()[$param->getName()] ??
            ($param->isOptional() ? $param->getDefaultValue() : null);

        case Body::class:
          $body = null;
          if (empty($paramAttributeArgs)) {
            $body = ($paramTypeName === 'string')
              ? json_encode($request->getBody())
              : $request->getBody();
          } else {
            $key = $param->getName();
            $body = $paramAttributeInstance->value ?? null;
          }

          return is_object($body) ? TypeManager::castObjectToUserType($body, $paramTypeName) : $body;

        case Req::class:
          return $request;

        case Res::class:
          return Response::getInstance();

        default:
          if (property_exists($paramAttributeInstance, 'value')) {
            return $paramAttributeInstance->value;
          }
      }
    }

    return null;
  }

  /**
   * Resolves a module.
   *
   * @param ReflectionClass $reflectionClass The module reflection class.
   * @return Module|null The resolved module if found, null otherwise.
   */
  public function resolveModule(ReflectionClass $reflectionClass): ?Module
  {
    $moduleAttributes = $reflectionClass->getAttributes(Module::class);

    if (empty($moduleAttributes)) {
      return null;
    }

    $moduleAttribute = array_pop($moduleAttributes);

    return $moduleAttribute->newInstance();
  }

  /**
   * Resolves a module.
   *
   * @param ReflectionClass $reflectionClass The module reflection class.
   * @return Module|null The resolved module if found, null otherwise.
   */
  public function resolveComponent(ReflectionClass $reflectionClass): ?Component
  {
    $componentAttributes = $reflectionClass->getAttributes(Component::class);

    if (empty($componentAttributes)) {
      return null;
    }

    $componentAttribute = array_pop($componentAttributes);

    return $componentAttribute->newInstance();
  }

  /**
   * Determines if a class is a module.
   *
   * @param string $id The class name.
   * @return bool True if the class is a module, false otherwise.
   */
  private function isModule(ReflectionClass $reflectionClass): bool
  {
    $moduleAttributes = $reflectionClass->getAttributes(Module::class);

    return !empty($moduleAttributes);
  }

  /**
   * Determines if a class is a component.
   *
   * @param ReflectionClass $reflectionClass The class to inspect.
   * @return bool True if the class is a component, false otherwise.
   */
  public function isComponent(ReflectionClass $reflectionClass): bool
  {
    $componentAttributes = $reflectionClass->getAttributes(Component::class);

    return !empty($componentAttributes);
  }

  /**
   * Binds handler attributes to an instance.
   *
   * @param mixed $instance The instance to bind the attributes to.
   * @param \ReflectionAttribute[] $reflectionAttributes The reflection attributes.
   * @return mixed The instance with the bound attributes.
   */
  private function bindHandlerAttributes(mixed $instance, array $reflectionAttributes): mixed
  {
    foreach ($reflectionAttributes as $attribute) {
      switch ($attribute->getName()) {
        case Body::class:
          /** @var Body $attributeInstance */
          $attributeInstance = $attribute->newInstance();
          $instance = $attributeInstance->value;
          break;

        default:
      }
    }

    return $instance;
  }
}