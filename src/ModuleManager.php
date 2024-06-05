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
   * Returns the singleton instance of the ModuleManager.
   *
   * @return ModuleManager The singleton instance of the ModuleManager.
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
   * Builds a list of all the imported module tokens.
   *
   * @param string $rootToken The root token.
   * @return void
   * @throws HttpException If the module is invalid.
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

      # 4. For each import, build the module tokens list
      foreach ($args['imports'] ?? [] as $import)
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
   * Builds a map of all the declared components.
   *
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
   * Returns a list of all the imported module tokens.
   *
   * @return ReflectionAttribute[] A list of all the imported module tokens.
   */
  public function getModuleTokens(): array
  {
    return $this->moduleTokens;
  }

  /**
   * Returns a list of all the imported module tokens.
   *
   * @return array A list of all the imported module tokens.
   */
  public function getProviderTokens(): array
  {
    return $this->providerTokens;
  }

  /**
   * Determines if the given module is valid.
   *
   * @param array $lastLoadedAttributes The last loaded attributes.
   * @return bool True if the given module is valid, false otherwise.
   */
  private function isValidModule(array $lastLoadedAttributes): bool
  {
    return !empty($lastLoadedAttributes);
  }

  /**
   * Builds a list of all the provider tokens.
   *
   * @return void
   * @throws EntryNotFoundException If the provider is not found.
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
   * Validates the given provider.
   *
   * @param string $tokenId The token ID of the provider.
   * @return ReflectionAttribute|null The reflection class of the provider.
   * @throws EntryNotFoundException If the provider is not found.
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
   * Loads the module attributes.
   *
   * @param ReflectionClass $reflectionClass The reflection class.
   * @return array The module attributes.
   */
  private function loadModuleAttributes(ReflectionClass $reflectionClass): array
  {
    return $reflectionClass->getAttributes(Module::class);
  }

  /**
   * Returns the config value for the given key.
   *
   * @param string $key The config key.
   * @return mixed The config value.
   */
  public function getConfig(string $key): mixed
  {
    return $this->config[$key] ?? null;
  }

  /**
   * Returns a list of all the declared components.
   *
   * @return Component[] A list of all the declared components.
   */
  public function getDeclaredAttributes(): array
  {
    return $this->declaredAttributes;
  }

  /**
   * Returns a list of all the declared components.
   *
   * @return ReflectionClass[] A list of all the declared components.
   */
  public function getDeclaredReflections(): array
  {
    return $this->declaredReflections;
  }

  /**
   * Returns a list of all the declared components.
   *
   * @return object[] A list of all the declared components.
   */
  public function getDeclarations(): array
  {
    return $this->declaredClassInstances;
  }

  /**
   * Returns the component attribute for the given reflection.
   *
   * @param ReflectionClass $reflection The reflection class.
   * @return false|Component The component attribute if found, false otherwise.
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