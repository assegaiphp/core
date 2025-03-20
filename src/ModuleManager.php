<?php

namespace Assegai\Core;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Interfaces\SingletonInterface;
use Assegai\Core\Util\Debug\Log;
use Assegai\Util\Path;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * The ModuleManager class is responsible for managing the modules.
 *
 * @package Assegai\Core
 */
class ModuleManager implements SingletonInterface
{
  /**
   * @var ModuleManager|null The singleton instance of the ModuleManager.
   */
  protected static ?ModuleManager $instance = null;
  /** @var ReflectionAttribute[] $lastLoadedAttributes */
  protected array $lastLoadedAttributes = [];
  /**
   * @var ReflectionAttribute[] A list of all the imported module tokens
   */
  protected array $moduleTokens = [];
  /**
   * @var array<class-string> A list of all the controller tokens
   */
  protected array $controllerTokensList = [];
  /**
   * @var array<class-string> A list of all the imported module tokens
   */
  protected array $providerTokens = [];
  /**
   * @var array<class-string> A list of all the declared component class names.
   */
  protected array $declarationTokens = [];
  /**
   * @var array<string, Component> A map of all the declared component attributes.
   */
  protected array $declaredAttributes = [];
  /**
   * @var array<string, ReflectionClass> A map of all the declared component reflections.
   */
  protected array $declaredReflections = [];
  /**
   * @var array<string, object> A map of all the declared components.
   */
  protected array $declaredClassInstances = [];
  /**
   * @var array<string, string> A map of all the declared styles.
   */
  protected array $declaredStyles = [];
  /**
   * @var array<string, mixed> The configuration.
   */
  protected array $config = [];
  /**
   * @var Injector The injector.
   */
  protected Injector $injector;
  /**
   * @var class-string The root module class.
   */
  protected string $rootModuleClass = '';
  /**
   * @var LoggerInterface The logger.
   */
  protected LoggerInterface $logger;
  /**
   * @var int The build iteration.
   */
  static int $buildIteration = 0;
  protected array $reflectionCache = ['rootToken' => []];
  protected array $attributesCache = [];
  /**
   * @var array A list of all the loaded styles.
   */
  protected array $loadedStyles = [];
  /**
   * @var array<class-string> A list of all the built declarations.
   */
  protected array $builtDeclarations = [];


  /**
   * Constructs a ModuleManager
   */
  private final function __construct()
  {
    $this->injector = Injector::getInstance();
    $this->logger = new ConsoleLogger(new ConsoleOutput());
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
   * Sets the root module class. This is the entry point of the application.
   *
   * @param string $rootModuleClass The root module class.
   * @return void
   */
  public function setRootModuleClass(string $rootModuleClass): void
  {
    $this->rootModuleClass = $rootModuleClass;
  }

  /**
   * Returns the root module class.
   *
   * @return string The root module class.
   */
  public function getRootModuleClass(): string
  {
    return $this->rootModuleClass;
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
    try {
      $stack = [$rootToken]; // Stack to simulate recursion
      $processedTokens = []; // Prevents redundant processing

      while (!empty($stack)) {
        $currentToken = array_pop($stack); // Process one module at a time

        if (isset($processedTokens[$currentToken])) {
          continue; // Skip already processed modules
        }

        // Cache ReflectionClass instances to avoid redundant reflection calls
        if (!isset($this->reflectionCache['rootToken'][$currentToken])) {
          $this->reflectionCache['rootToken'][$currentToken] = new ReflectionClass($currentToken);
        }

        // Load attributes (with caching)
        if (isset($this->attributesCache[$currentToken])) {
          $this->lastLoadedAttributes = $this->attributesCache[$currentToken];
        } else {
          $this->lastLoadedAttributes = $this->loadModuleAttributes($this->reflectionCache['rootToken'][$currentToken]);
          $this->attributesCache[$currentToken] = $this->lastLoadedAttributes;
        }

        if (!$this->isValidModule($this->lastLoadedAttributes)) {
          Log::error(__CLASS__, "Invalid Token ID: $currentToken");
          throw new HttpException();
        }

        $reflectionModuleAttribute = $this->lastLoadedAttributes[0];

        /** @var array{declarations: string[], imports: string[], exports: string[], providers: string[], controllers: string[], config: array<string, mixed>} $args */
        $args = $reflectionModuleAttribute->getArguments();

        // Add module to processed list
        $processedTokens[$currentToken] = true;

        // Store module metadata
        $this->moduleTokens[$currentToken] = $reflectionModuleAttribute;

        // Merge config values
        if (!empty($args['config'])) {
          foreach ($args['config'] as $key => $value) {
            $this->config[$key] = $value;
          }
        }

        // Merge declarations
        if (!empty($args['declarations'])) {
          foreach ($args['declarations'] as $declaration) {
            $this->declarationTokens[] = $declaration;
          }
        }

        // Push imports onto the stack for later processing
        foreach ($args['imports'] ?? [] as $import) {
          if (!isset($processedTokens[$import])) {
            $stack[] = $import;
          }
        }

        // Process exports
        foreach ($args['exports'] ?? [] as $export) {
          if (!isset($processedTokens[$export])) {
            $resolvedExport = $this->injector->resolve($export);

            if ($resolvedExport instanceof Module) {
              $stack[] = $export;
            } else {
              $this->injector->add($export, $resolvedExport);
            }
          }
        }
      }
    } catch (ReflectionException $e) {
      throw new HttpException($e->getMessage());
    }
  }

  /**
   * Builds a map of all the declared components.
   *
   * @return void
   * @throws HttpException If something went wrong while building the declaration map.
   */
  public function buildDeclarationMap(): void
  {
    try {
      foreach ($this->declarationTokens as $declarationToken) {
        if (in_array($declarationToken, $this->builtDeclarations)) {
          continue;
        }
        $componentClassReflection = new ReflectionClass($declarationToken);
        $componentAttributeInstance = $this->getComponentAttribute($componentClassReflection);

        if (!$componentAttributeInstance) {
          continue;
        }

        // TODO: Refactor declarations to allow components, directives and pipes
        // See https://angular.io/guide/feature-modules
        $this->declaredAttributes[$componentAttributeInstance->selector] = $componentAttributeInstance;
        $this->declaredReflections[$componentAttributeInstance->selector] = $componentClassReflection;
        $this->declaredClassInstances[$componentAttributeInstance->selector] = $componentClassReflection->newInstance();
        $this->declaredStyles[$componentAttributeInstance->selector] = '';

        if ($componentAttributeInstance->styleUrls) {
          foreach ($componentAttributeInstance->styleUrls as $styleUrl) {
            $stylesheetFilename = Path::normalize(Path::join(dirname($componentClassReflection->getFileName()), $styleUrl));
            if (in_array($stylesheetFilename, $this->loadedStyles)) {
              continue;
            }
            $this->declaredStyles[$componentAttributeInstance->selector] .=
              file_get_contents($stylesheetFilename) ?: throw new HttpException("Failed to read stylesheet file $stylesheetFilename.");
            $this->loadedStyles[] = $stylesheetFilename;
          }
        } else {
          foreach ($componentAttributeInstance->styles as $style) {
            $this->declaredStyles[$componentAttributeInstance->selector] .= $style;
          }
        }

        $this->builtDeclarations[] = $declarationToken;
      }
    } catch (ReflectionException $exception) {
      throw new HttpException($exception->getMessage());
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
   * @throws ContainerException
   * @throws ReflectionException
   */
  public function buildProviderTokensList(): void
  {
    foreach ($this->moduleTokens as $module) {
      /** @var array{imports: string[], exports: string[], providers: string[]} $args */
      $args = $module->getArguments();

      foreach ($args['providers'] ?? [] as $tokenId) {
        if ($provider = $this->validateProvider($tokenId)) {
          $this->providerTokens[$tokenId] = $provider;
          $instance = $this->injector->resolve($tokenId);

          if ($instance) {
            $this->injector->add($tokenId, $instance);
          }
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
    try {
      if (!isset($this->reflectionCache[$tokenId])) {
        $this->reflectionCache[$tokenId] = new ReflectionClass($tokenId);
      }
      $reflectionClass = $this->reflectionCache[$tokenId];
      $className = $reflectionClass->getName();

      if (!isset($this->attributesCache['injectable'][$className])) {
        $this->attributesCache['injectable'][$className] = $reflectionClass->getAttributes(Injectable::class);
      }

      $this->lastLoadedAttributes = $this->attributesCache['injectable'][$className];
      return !empty($this->lastLoadedAttributes) ? $reflectionClass : null;
    } catch (ReflectionException) {
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
   * Returns a list of all the declared styles.
   *
   * @return string[] A list of all the declared styles.
   */
  public function getDeclaredStyles(): array
  {
    return $this->declaredStyles;
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

    if (!$componentAttributes) {
      return false;
    }

    return $componentAttributes[0]->newInstance();
  }

}