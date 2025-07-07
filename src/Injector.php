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
use Assegai\Core\Queues\Attributes\InjectQueue;
use Assegai\Core\Util\TypeManager;
use Codeception\Attribute\Skip;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

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
   * @var ReflectionClass[] The cache of reflections.
   */
  protected array $reflectionClassCache = [];

  /**
   * @var ReflectionAttribute[] The cache of attributes.
   */
  protected array $reflectionAttributesCache = ['injectable' => [], 'module' => [], 'component' => [], 'controller' => []];
  /**
   * @var LoggerInterface The logger instance.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new Injector instance.
   */
  private final function __construct()
  {
    $this->logger = new ConsoleLogger(new ConsoleOutput());
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
    try {
      $stack = [$id]; // Stack to manage iterative processing
      $resolvedInstances = []; // Cache for resolved instances

      while (!empty($stack)) {
        $currentId = array_pop($stack);

        // If already resolved, return from cache
        if (isset($resolvedInstances[$currentId])) {
          continue;
        }

        // Retrieve or cache ReflectionClass instance
        if (!isset($this->reflectionClassCache[$currentId])) {
          $this->reflectionClassCache[$currentId] = new ReflectionClass($currentId);
        }
        $reflectionClass = $this->reflectionClassCache[$currentId];

        $attributeReflections = $reflectionClass->getAttributes();

        // Check if class is injectable
        if ($this->isNotInjectable($reflectionClass)) {
          if ($this->isModule($reflectionClass)) {
            $resolvedInstances[$currentId] = $this->resolveModule($reflectionClass);
            continue;
          }

          if ($this->isComponent($reflectionClass)) {
            $resolvedInstances[$currentId] = $this->resolveComponent($reflectionClass);
            continue;
          }

          throw new ContainerException("$currentId is not injectable");
        }

        // Handle non-instantiable classes
        if (!$reflectionClass->isInstantiable()) {
          if ($reflectionClass->hasMethod('getInstance')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $resolvedInstances[$currentId] = $this->bindHandlerAttributes($currentId::getInstance(), $attributeReflections);
            continue;
          }

          if ($reflectionClass->hasMethod('newInstance')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $resolvedInstances[$currentId] = $this->bindHandlerAttributes($currentId::newInstance(), $attributeReflections);
            continue;
          }

          throw new ContainerException("Class '$currentId' is not instantiable");
        }

        // Inspect constructor for dependencies
        $constructor = $reflectionClass->getConstructor();

        // No constructor -> instantiate directly
        if (!$constructor) {
          $resolvedInstances[$currentId] = $this->bindHandlerAttributes(new $currentId, $attributeReflections);
          continue;
        }

        $parameters = $constructor->getParameters();

        // No dependencies -> instantiate directly
        if (empty($parameters)) {
          $resolvedInstances[$currentId] = $this->bindHandlerAttributes(new $currentId, $attributeReflections);
          continue;
        }

        // Resolve dependencies iteratively
        $dependencies = $this->resolveDependencies(id: $currentId, parameters: $parameters);
        $resolvedInstances[$currentId] = $this->bindHandlerAttributes($reflectionClass->newInstanceArgs($dependencies), $attributeReflections);
      }

      return $resolvedInstances[$id] ?? null;
    } catch (ReflectionException) {
      throw new ContainerException("$id is not a valid ID");
    }
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
    if (!isset($this->reflectionAttributesCache['injectable'][$reflectionClass->getName()])) {
      $this->reflectionAttributesCache['injectable'][$reflectionClass->getName()] = $reflectionClass->getAttributes(CoreInjectable::class);

      if (empty($this->reflectionAttributesCache['injectable'][$reflectionClass->getName()])) {
        $this->reflectionAttributesCache['injectable'][$reflectionClass->getName()] = $reflectionClass->getAttributes(Injectable::class);
      }
    }

    return !empty($this->reflectionAttributesCache['injectable'][$reflectionClass->getName()]);
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

      if (! ($paramType instanceof ReflectionNamedType) || $paramType->isBuiltin()) {
        return null;
      }

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

      # Resolve repository injection
      $repositoryAttributes = $param->getAttributes('Assegai\Orm\Attributes\InjectRepository');

      foreach ( $repositoryAttributes as $reflectionRepoAttr ) {
        $injectRepositoryInstance = $reflectionRepoAttr->newInstance();
        if (property_exists($injectRepositoryInstance, 'repository')) {
          return $injectRepositoryInstance->repository;
        }
      }

      # TODO: Check if param has Injectable class or attribute
      $repositoryAttributes = [...$param->getAttributes(CoreInjectable::class), ...$param->getAttributes(Injectable::class)];

      # Resolve queue injection
      $queueAttributes = $param->getAttributes(InjectQueue::class);

      foreach ( $queueAttributes as $queueAttribute ) {
        $queueInstance = $queueAttribute->newInstance();
        if (property_exists($queueInstance, 'queue')) {
          return $queueInstance->queue;
        }
      }

      return $this->get($paramType->getName());
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
    $className = $reflectionClass->getName();

    if (!isset($this->reflectionAttributesCache['module'][$className])) {
      $this->reflectionAttributesCache['module'][$className] = $reflectionClass->getAttributes(Module::class);
    }

    $moduleAttributes = $this->reflectionAttributesCache['module'][$className];

    if (!$moduleAttributes) {
      return null;
    }

    return $moduleAttributes[0];
  }

  /**
   * Resolves a module.
   *
   * @param ReflectionClass $reflectionClass The module reflection class.
   * @return Module|null The resolved module if found, null otherwise.
   */
  public function resolveComponent(ReflectionClass $reflectionClass): ?Component
  {
    $className = $reflectionClass->getName();

    if (!isset($this->reflectionAttributesCache['component'][$className])) {
      $this->reflectionAttributesCache['component'][$className] = $reflectionClass->getAttributes(Component::class);
    }

    $componentAttributes = $this->reflectionAttributesCache['component'][$className];

    if (empty($componentAttributes)) {
      return null;
    }

    return $componentAttributes[0];
  }

  /**
   * Determines if a class is a module.
   *
   * @param string $id The class name.
   * @return bool True if the class is a module, false otherwise.
   */
  private function isModule(ReflectionClass $reflectionClass): bool
  {
    if (!isset($this->reflectionAttributesCache['module'][$reflectionClass->getName()])) {
      $this->reflectionAttributesCache['module'][$reflectionClass->getName()] = $reflectionClass->getAttributes(Module::class);
    }

    return !empty($this->reflectionAttributesCache['module'][$reflectionClass->getName()]);
  }

  /**
   * Determines if a class is a component.
   *
   * @param ReflectionClass $reflectionClass The class to inspect.
   * @return bool True if the class is a component, false otherwise.
   */
  public function isComponent(ReflectionClass $reflectionClass): bool
  {
    $className = $reflectionClass->getName();

    if (!isset($this->reflectionAttributesCache['component'][$className])) {
      $this->reflectionAttributesCache['component'][$className] = $reflectionClass->getAttributes(Component::class);
    }

    return !empty($this->reflectionAttributesCache['component'][$className]);
  }

  protected array $attributeInstances = [];

  /**
   * Binds handler attributes to an instance.
   *
   * @param mixed $instance The instance to bind the attributes to.
   * @param ReflectionAttribute[] $reflectionAttributes The reflection attributes.
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