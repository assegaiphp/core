<?php

namespace Assegai\Core;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Util\Debug\Log;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;

class ModuleManager
{
  protected static ?ModuleManager $instance = null;

  /** @var ReflectionAttribute[] $lastLoadedAttributes */
  protected array $lastLoadedAttributes = [];

  /**
   * @var ReflectionAttribute[] A list of all the imported module tokens
   */
  protected array $moduleTokens = [];

  /**
   * @var array A list of all the controller tokens
   */
  protected array $controllerTokensList = [];

  /**
   * @var array A list of all the imported module tokens
   */
  protected array $providerTokens = [];

  /**
   * @var array A list of all the declared component class names.
   */
  protected array $declarationTokens = [];

  /**
   * @var array A map of all the declared component attributes.
   */
  protected array $declaredAttributes = [];

  /**
   * @var array A map of all the declared component reflections.
   */
  protected array $declaredReflections = [];

  /**
   * @var array A map of all the declared components.
   */
  protected array $declaredClassInstances = [];

  /**
   * @var array
   */
  protected array $config = [];

  /**
   * Constructs a ModuleManager
   */
  private final function __construct()
  {
  }

  /**
   * @return ModuleManager
   */
  public static function getInstance(): ModuleManager
  {
    if (empty(self::$instance))
    {
      self::$instance = new ModuleManager();
    }

    return self::$instance;
  }

  /**
   * @param string $rootToken
   * @return void
   * @throws HttpException
   */
  public function buildModuleTokensList(string $rootToken): void
  {
    try
    {
      $reflectionModule = new ReflectionClass($rootToken);
      $this->lastLoadedAttributes = $this->loadModuleAttributes($reflectionModule);

      if (!$this->isValidModule($this->lastLoadedAttributes))
      {
        Log::error(__CLASS__, "Invalid Token ID: $rootToken");
        throw new HttpException();
      }
      $reflectionModuleAttribute = $this->lastLoadedAttributes[0];

      /** @var ['declarations' => 'array', 'imports' => 'array', 'exports' => 'array', 'providers' => 'array', 'controllers' => 'array', 'config' => 'array'] $args */
      $args = $reflectionModuleAttribute->getArguments();

      # 1. Add rootToken to the list
      $this->moduleTokens[$rootToken] = $reflectionModuleAttribute;

      # 2. Store config
      if (isset($args['config']))
      {
        $this->config = array_merge($this->config, $args['config']);
      }

      # 3. Store declarations
      if (isset($args['declarations']))
      {
        $this->declarationTokens = array_merge($this->declarationTokens, $args['declarations']);
      }

      # 4. For each import
      foreach ($args['imports'] as $import)
      {
        $this->buildModuleTokensList($import);
      }
    }
    catch (ReflectionException $e)
    {
      throw new HttpException($e->getMessage());
    }
  }

  /**
   * @return void
   */
  public function buildDeclarationMap(): void
  {
    try
    {
      foreach ($this->declarationTokens as $declarationToken)
      {
        $componentClassReflection = new ReflectionClass($declarationToken);
        $componentAttribute = $this->getComponentAttribute($componentClassReflection);

        if (!$componentAttribute)
        {
          continue;
        }

        // TODO: Refactor declarations to allow components, directives and pipes
        // See https://angular.io/guide/feature-modules
        $this->declaredAttributes[$componentAttribute->selector] = $componentAttribute;
        $this->declaredReflections[$componentAttribute->selector] = $componentClassReflection;
        $this->declaredClassInstances[$componentAttribute->selector] = $componentClassReflection->newInstance();
      }
    }
    catch (ReflectionException $exception)
    {
      die(new HttpException($exception->getMessage()));
    }
  }

  /**
   * @return ReflectionAttribute[]
   */
  public function getModuleTokens(): array
  {
    return $this->moduleTokens;
  }

  /**
   * @return array
   */
  public function getProviderTokens(): array
  {
    return $this->providerTokens;
  }

  /**
   * @param array $lastLoadedAttributes
   * @return bool
   */
  private function isValidModule(array $lastLoadedAttributes): bool
  {
    return !empty($lastLoadedAttributes);
  }

  /**
   * @return void
   * @throws EntryNotFoundException
   */
  public function buildProviderTokensList(): void
  {
    foreach ($this->moduleTokens as $module)
    {
      /** @var ['imports' => 'array', 'exports' => 'array', 'providers' => 'array'] $args */
      $args = $module->getArguments();

      foreach ($args['providers'] as $tokenId)
      {
        if ($provider = $this->validateProvider($tokenId))
        {
          $this->providerTokens[$tokenId] = $provider;
        }
      }
    }
  }

  /**
   * @param string $tokenId
   * @return ReflectionAttribute|null
   * @throws EntryNotFoundException
   */
  private function validateProvider(string $tokenId): ?ReflectionClass
  {
    try
    {
      $reflectionClass = new ReflectionClass($tokenId);
      $this->lastLoadedAttributes = $reflectionClass->getAttributes(Injectable::class);
      return !empty($this->lastLoadedAttributes) ? $reflectionClass : null;
    }
    catch (ReflectionException)
    {
      throw new EntryNotFoundException($tokenId);
    }
  }

  /**
   * @param ReflectionClass $reflectionClass
   * @return array
   */
  private function loadModuleAttributes(ReflectionClass $reflectionClass): array
  {
    return $reflectionClass->getAttributes(Module::class);
  }

  /**
   * @param string $key
   * @return mixed
   */
  public function getConfig(string $key): mixed
  {
    return $this->config[$key] ?? null;
  }

  /**
   * @return Component[]
   */
  public function getDeclaredAttributes(): array
  {
    return $this->declaredAttributes;
  }

  /**
   * @return ReflectionClass[]
   */
  public function getDeclaredReflections(): array
  {
    return $this->declaredReflections;
  }

  /**
   * @return object[]
   */
  public function getDeclarations(): array
  {
    return $this->declaredClassInstances;
  }

  /**
   * @param ReflectionClass $reflection
   * @return false|Component
   */
  private function getComponentAttribute(ReflectionClass $reflection): false|Component
  {
    $componentAttributes = $reflection->getAttributes(Component::class);

    if (!$componentAttributes)
    {
      return false;
    }

    return $componentAttributes[0]->newInstance();
  }

}