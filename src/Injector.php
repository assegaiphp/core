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
use Assegai\Core\Enumerations\Scope;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Container\ResolveException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Responders\Responder;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\IContainer;
use Assegai\Core\Interfaces\IEntryNotFoundException;
use Assegai\Core\Interfaces\ParameterResolverInterface;
use Assegai\Core\Interfaces\ITokenStoreOwner;
use Assegai\Core\Queues\Attributes\InjectQueue;
use Assegai\Core\Runtimes\RuntimeContext;
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
   * @var array<class-string, Scope>
   */
  protected array $scopeCache = [];
  /**
   * @var array<class-string, bool>
   */
  protected array $requestScopedDependencyCache = [];
  /**
   * @var ParameterResolverInterface[]
   */
  protected array $parameterResolvers = [];
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
   * Creates a fresh injector instance and promotes it to the active singleton for compatibility.
   *
   * @return Injector
   */
  public static function createFresh(): Injector
  {
    self::$instance = new Injector();
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
    $scopedInstance = RuntimeContext::get($id);

    if (null !== $scopedInstance) {
      return $scopedInstance;
    }

    if ($this->has($id)) {
      return $this->get($id);
    }

    try {
      $stack = [$id]; // Stack to manage iterative processing
      $resolvedInstances = []; // Cache for resolved instances

      while (!empty($stack)) {
        $currentId = array_pop($stack);

        // If already resolved, return from cache
        if (isset($resolvedInstances[$currentId])) {
          continue;
        }

        $scopedInstance = RuntimeContext::get($currentId);

        if (null !== $scopedInstance) {
          $resolvedInstances[$currentId] = $scopedInstance;
          continue;
        }

        if ($this->has($currentId)) {
          $resolvedInstances[$currentId] = $this->get($currentId);
          continue;
        }

        // Retrieve or cache ReflectionClass instance
        if (!isset($this->reflectionClassCache[$currentId])) {
          $this->reflectionClassCache[$currentId] = new ReflectionClass($currentId);
        }
        $reflectionClass = $this->reflectionClassCache[$currentId];
        $scope = $this->getDependencyScope($currentId, $reflectionClass);

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
            $this->storeResolvedDependency($currentId, $resolvedInstances[$currentId], $scope);
            continue;
          }

          if ($reflectionClass->hasMethod('newInstance')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $resolvedInstances[$currentId] = $this->bindHandlerAttributes($currentId::newInstance(), $attributeReflections);
            $this->storeResolvedDependency($currentId, $resolvedInstances[$currentId], $scope);
            continue;
          }

          throw new ContainerException("Class '$currentId' is not instantiable");
        }

        // Inspect constructor for dependencies
        $constructor = $reflectionClass->getConstructor();

        // No constructor -> instantiate directly
        if (!$constructor) {
          $resolvedInstances[$currentId] = $this->bindHandlerAttributes(new $currentId, $attributeReflections);
          $this->storeResolvedDependency($currentId, $resolvedInstances[$currentId], $scope);
          continue;
        }

        $parameters = $constructor->getParameters();

        // No dependencies -> instantiate directly
        if (empty($parameters)) {
          $resolvedInstances[$currentId] = $this->bindHandlerAttributes(new $currentId, $attributeReflections);
          $this->storeResolvedDependency($currentId, $resolvedInstances[$currentId], $scope);
          continue;
        }

        // Resolve dependencies iteratively
        $dependencies = $this->resolveDependencies(id: $currentId, parameters: $parameters);
        $resolvedInstances[$currentId] = $this->bindHandlerAttributes($reflectionClass->newInstanceArgs($dependencies), $attributeReflections);
        $this->storeResolvedDependency($currentId, $resolvedInstances[$currentId], $scope);
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
   * Retains only the provided dependency IDs in the container store.
   *
   * @param string[] $entryIds
   * @return void
   */
  public function retain(array $entryIds): void
  {
    $allowed = array_fill_keys($entryIds, true);

    foreach (array_keys($this->store) as $entryId) {
      if (!isset($allowed[$entryId])) {
        unset($this->store[$entryId]);
      }
    }
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

      [$isAttributeResolved, $attributeResolvedValue] = $this->resolveDependencyFromParameterAttributes($param);
      if ($isAttributeResolved) {
        return $attributeResolvedValue;
      }

      [$isResolverResolved, $resolverResolvedValue] = $this->resolveDependencyFromResolvers($param);
      if ($isResolverResolved) {
        return $resolverResolvedValue;
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

      # TODO: Check if param has Injectable class or attribute
      $repositoryAttributes = [...$param->getAttributes(CoreInjectable::class), ...$param->getAttributes(Injectable::class)];

      $typeName = $paramType->getName();
      $frameworkDependency = match ($typeName) {
        Request::class,
        RequestInterface::class => Request::current(),
        Response::class,
        ResponseInterface::class => Response::current(),
        ResponseEmitterInterface::class => RuntimeContext::get(ResponseEmitterInterface::class)
          ?? $this->get(ResponseEmitterInterface::class)
          ?? new PhpResponseEmitter(),
        Responder::class,
        ResponderInterface::class => Responder::current(),
        default => null,
      };

      if (null !== $frameworkDependency) {
        if (
          !in_array($typeName, [
            Request::class,
            RequestInterface::class,
            Response::class,
            ResponseInterface::class,
            Responder::class,
            ResponderInterface::class,
            ResponseEmitterInterface::class,
          ], true)
          && !$this->has($typeName)
        ) {
          $this->add($typeName, $frameworkDependency);
        }

        return $frameworkDependency;
      }

      try {
        $this->assertDependencyAccessible($id, $typeName);

        $dependency = $this->get($typeName);

        if (null === $dependency) {
          $dependency = $this->resolve($typeName);
        }
      } catch (ContainerException $exception) {
        if ($param->isDefaultValueAvailable()) {
          return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
          return null;
        }

        throw new ResolveException(id: $id, message: "$resolveErrorPrefix — {$exception->getMessage()}");
      }

      if (null === $dependency) {
        if ($param->isDefaultValueAvailable()) {
          return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
          return null;
        }

        throw new ResolveException(id: $id, message: "$resolveErrorPrefix — Unable to resolve $typeName");
      }

      if (
        !in_array($this->getDependencyScope($typeName), [Scope::REQUEST, Scope::TRANSIENT], true)
        && !$this->has($typeName)
      ) {
        $this->add($typeName, $dependency);
      }

      return $dependency;
    }, $parameters);
  }

  /**
   * Allows parameter attributes to provide their own resolved dependency value without the injector
   * hardcoding package-specific attribute names.
   *
   * Any attribute that defines a public `resolveParameterValue()` method participates in this seam.
   *
   * @param ReflectionParameter $param
   * @return array{0: bool, 1: mixed}
   */
  private function resolveDependencyFromParameterAttributes(ReflectionParameter $param): array
  {
    foreach ($param->getAttributes() as $attributeReflection) {
      $attributeInstance = $attributeReflection->newInstance();

      if (!method_exists($attributeInstance, 'resolveParameterValue')) {
        continue;
      }

      return [true, $attributeInstance->resolveParameterValue()];
    }

    return [false, null];
  }

  /**
   * Allows registered package resolvers to provide dependency values for parameter metadata
   * without hardcoding those packages into the injector itself.
   *
   * @param ReflectionParameter $param
   * @return array{0: bool, 1: mixed}
   */
  private function resolveDependencyFromResolvers(ReflectionParameter $param): array
  {
    foreach ($this->parameterResolvers as $resolver) {
      if (!$resolver->supports($param, $this)) {
        continue;
      }

      return [true, $resolver->resolve($param, $this)];
    }

    return [false, null];
  }

  public function registerParameterResolver(ParameterResolverInterface $resolver): void
  {
    foreach ($this->parameterResolvers as $registeredResolver) {
      if ($registeredResolver::class === $resolver::class) {
        return;
      }
    }

    $this->parameterResolvers[] = $resolver;
  }

  /**
   * @return ParameterResolverInterface[]
   */
  public function getParameterResolvers(): array
  {
    return $this->parameterResolvers;
  }


  /**
   * Resolves a dependency for a specific consumer after verifying module export visibility.
   *
   * @param string $consumerId
   * @param string $dependencyId
   * @return mixed
   * @throws ContainerException|ReflectionException
   */
  public function resolveForConsumer(string $consumerId, string $dependencyId): mixed
  {
    $this->assertDependencyAccessible($consumerId, $dependencyId);

    return $this->resolve($dependencyId);
  }

  /**
   * Verifies that a consumer can access the requested provider according to module exports.
   *
   * @param string $consumerId
   * @param string $dependencyId
   * @return void
   * @throws ContainerException
   */
  private function assertDependencyAccessible(string $consumerId, string $dependencyId): void
  {
    $moduleManager = ModuleManager::getInstance();
    $consumerModule = $this->resolveConsumerModule($consumerId, $moduleManager);

    if (null === $consumerModule) {
      return;
    }

    if ($moduleManager->canModuleAccessProvider($consumerModule, $dependencyId)) {
      return;
    }

    $ownerModule = $moduleManager->getProviderOwnerModule($dependencyId) ?? 'an inaccessible module';

    throw new ContainerException(sprintf(
      "%s cannot access %s because %s does not export it.",
      $consumerId,
      $dependencyId,
      $ownerModule,
    ));
  }

  /**
   * Resolves the owning module for a consumer class when it belongs to the application graph.
   *
   * @param string $consumerId
   * @param ModuleManager $moduleManager
   * @return string|null
   */
  private function resolveConsumerModule(string $consumerId, ModuleManager $moduleManager): ?string
  {
    $providerOwner = $moduleManager->getProviderOwnerModule($consumerId);

    if (null !== $providerOwner) {
      return $providerOwner;
    }

    $controllerOwner = ControllerManager::getInstance()->getOwningModule($consumerId);

    if (null !== $controllerOwner) {
      return $controllerOwner;
    }

    if (array_key_exists($consumerId, $moduleManager->getModuleTokens())) {
      return $consumerId;
    }

    return null;
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
          return Response::current();

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
   * Returns the effective lifetime scope for a dependency.
   *
   * Dependencies that explicitly opt into `REQUEST` or `TRANSIENT` keep that scope.
   * For default-scoped injectables, Assegai automatically upgrades them to `REQUEST`
   * when their constructor graph captures request-bound framework objects.
   *
   * @param class-string $id
   * @param ReflectionClass|null $reflectionClass
   * @return Scope
   * @throws ReflectionException
   */
  public function getDependencyScope(string $id, ?ReflectionClass $reflectionClass = null): Scope
  {
    if (isset($this->scopeCache[$id])) {
      return $this->scopeCache[$id];
    }

    $reflectionClass ??= $this->reflectionClassCache[$id] ?? new ReflectionClass($id);
    $declaredScope = $this->getDeclaredScope($reflectionClass);

    if ($declaredScope !== Scope::DEFAULT) {
      return $this->scopeCache[$id] = $declaredScope;
    }

    if ($this->capturesRequestScopedDependencies($reflectionClass)) {
      return $this->scopeCache[$id] = Scope::REQUEST;
    }

    return $this->scopeCache[$id] = Scope::DEFAULT;
  }

  /**
   * Persists a resolved dependency according to its effective lifetime.
   *
   * @param string $id
   * @param mixed $instance
   * @param Scope $scope
   * @return void
   */
  private function storeResolvedDependency(string $id, mixed $instance, Scope $scope): void
  {
    match ($scope) {
      Scope::REQUEST => RuntimeContext::set($id, $instance),
      Scope::TRANSIENT => null,
      default => $this->add($id, $instance),
    };
  }

  /**
   * Returns the declared scope from the Injectable attribute options.
   *
   * @param ReflectionClass $reflectionClass
   * @return Scope
   */
  private function getDeclaredScope(ReflectionClass $reflectionClass): Scope
  {
    $className = $reflectionClass->getName();

    if (!isset($this->reflectionAttributesCache['injectable'][$className])) {
      $this->isInjectable($reflectionClass);
    }

    $attributes = $this->reflectionAttributesCache['injectable'][$className] ?? [];

    if ($attributes === []) {
      return Scope::DEFAULT;
    }

    $attribute = $attributes[0]->newInstance();
    $options = $attribute->options ?? null;

    if ($options instanceof ScopeOptions) {
      return $options->scope;
    }

    if (is_array($options) && isset($options['scope']) && $options['scope'] instanceof Scope) {
      return $options['scope'];
    }

    return Scope::DEFAULT;
  }

  /**
   * Detects whether a dependency graph captures request-scoped framework objects.
   *
   * @param ReflectionClass $reflectionClass
   * @param array<int, string> $stack
   * @return bool
   * @throws ReflectionException
   */
  private function capturesRequestScopedDependencies(ReflectionClass $reflectionClass, array $stack = []): bool
  {
    $className = $reflectionClass->getName();

    if (isset($this->requestScopedDependencyCache[$className])) {
      return $this->requestScopedDependencyCache[$className];
    }

    if (in_array($className, $stack, true)) {
      return false;
    }

    $constructor = $reflectionClass->getConstructor();

    if (!$constructor || $constructor->getParameters() === []) {
      return $this->requestScopedDependencyCache[$className] = false;
    }

    $nextStack = [...$stack, $className];

    foreach ($constructor->getParameters() as $parameter) {
      $type = $parameter->getType();

      if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
        continue;
      }

      $dependencyName = $type->getName();

      if (in_array($dependencyName, [
        Request::class,
        RequestInterface::class,
        Response::class,
        ResponseInterface::class,
        Responder::class,
        ResponderInterface::class,
        ResponseEmitterInterface::class,
      ], true)) {
        return $this->requestScopedDependencyCache[$className] = true;
      }

      if (!class_exists($dependencyName) && !interface_exists($dependencyName)) {
        continue;
      }

      $dependencyReflection = $this->reflectionClassCache[$dependencyName] ?? new ReflectionClass($dependencyName);
      $this->reflectionClassCache[$dependencyName] = $dependencyReflection;

      if ($this->isInjectable($dependencyReflection) && $this->getDependencyScope($dependencyName, $dependencyReflection) === Scope::REQUEST) {
        return $this->requestScopedDependencyCache[$className] = true;
      }

      if ($this->isInjectable($dependencyReflection) && $this->capturesRequestScopedDependencies($dependencyReflection, $nextStack)) {
        return $this->requestScopedDependencyCache[$className] = true;
      }
    }

    return $this->requestScopedDependencyCache[$className] = false;
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
