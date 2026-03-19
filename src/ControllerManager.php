<?php

namespace Assegai\Core;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Attributes\Controller;
use Assegai\Core\Exceptions\Http\NotFoundException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * The ControllerManager class is responsible for managing the controllers.
 *
 * @package Assegai\Core
 */
class ControllerManager
{
  protected ModuleManager $moduleManager;
  /**
   * @var ControllerManager|null
   */
  protected static ?ControllerManager $instance = null;

  /** @var ReflectionAttribute[] $lastLoadedAttributes */
  protected array $lastLoadedAttributes = [];

  /** @var ReflectionClass[] $controllerTokensList */
  protected array $controllerTokensList = [];

  /** @var array $controllerPathTokenIdMap */
  protected array $controllerPathTokenIdMap = [];
  /**
   * @var array<class-string, ReflectionClass[]> A map of controller reflections keyed by module class.
   */
  protected array $moduleControllerTokensMap = [];
  /**
   * @var array<class-string, string> A map of fully resolved module branch prefixes keyed by module class.
   */
  protected array $moduleBranchPrefixMap = [];
  /**
   * @var array<class-string, array{module: class-string, local_path: string, resolved_path: string, hosts: array<int, string>}>
   */
  protected array $controllerRouteMetadata = [];

  /**
   * ControllerManager constructor.
   */
  private final function __construct()
  {
    $this->moduleManager = ModuleManager::getInstance();
  }

  /**
   * Returns the ControllerManager instance.
   *
   * @return ControllerManager
   */
  public static function getInstance(): ControllerManager
  {
    if (empty(self::$instance)) {
      self::$instance = new ControllerManager();
    }

    return self::$instance;
  }

  /**
   * Returns the controller token list.
   *
   * @return array<string, ReflectionClass>
   */
  public function getControllerTokenList(): array
  {
    return $this->controllerTokensList;
  }

  /**
   * Returns the controller path token id map.
   *
   * @return array<string, string>
   */
  public function getControllerPathTokenIdMap(): array
  {
    return $this->controllerPathTokenIdMap;
  }

  /**
   * Returns the controller reflections declared within the given module.
   *
   * @param string $moduleClass
   * @return array<string, ReflectionClass>
   */
  public function getModuleControllerTokens(string $moduleClass): array
  {
    return $this->moduleControllerTokensMap[$moduleClass] ?? [];
  }

  /**
   * Returns the resolved branch prefix for the given module.
   *
   * @param string $moduleClass
   * @return string
   */
  public function getModuleBranchPrefix(string $moduleClass): string
  {
    return $this->moduleBranchPrefixMap[$moduleClass] ?? '/';
  }

  /**
   * Returns the resolved route prefix for the given controller.
   *
   * @param string $controllerClass
   * @return string|null
   */
  public function getResolvedControllerPath(string $controllerClass): ?string
  {
    return $this->controllerRouteMetadata[$controllerClass]['resolved_path'] ?? null;
  }

  /**
   * Returns the configured host patterns for the given controller.
   *
   * @param string $controllerClass
   * @return array<int, string>
   */
  public function getControllerHosts(string $controllerClass): array
  {
    return $this->controllerRouteMetadata[$controllerClass]['hosts'] ?? [];
  }

  /**
   * Returns the root controller class when the root module declares one.
   *
   * Root modules may also act purely as composition roots that only import feature modules.
   * In that case there is no dedicated root controller and routing should continue through the
   * imported module tree instead of throwing.
   *
   * @return string|null
   * @throws ReflectionException
   */
  public function getRootControllerClass(): ?string
  {
    $rootModuleClass = $this->moduleManager->getRootModuleClass();
    $rootModuleReflection = new ReflectionClass($rootModuleClass);
    $attributes = $rootModuleReflection->getAttributes(Module::class);

    if (! $attributes ) {
      throw new RuntimeException('Root module class must be decorated with the Module attribute');
    }

    /** @var ReflectionAttribute $moduleAttributeReflection */
    $moduleAttributeReflection = array_pop($attributes);

    $rootControllersClasses = $moduleAttributeReflection->getArguments()['controllers'] ?? [];

    if (empty($rootControllersClasses)) {
      return null;
    }

    $rootControllerClass = '';

    foreach ($rootControllersClasses as $index => $controllersClass) {
      if ($index === 0) {
        $rootControllerClass = $controllersClass;
      }

      // Check if the controller has a path === '/'
      if (
        isset($this->controllerPathTokenIdMap[$controllersClass]) &&
        $this->controllerPathTokenIdMap[$controllersClass] === '/'
      ) {
        $rootControllerClass = $controllersClass;
        break;
      }
    }

    return $rootControllerClass;
  }

  /**
   * Builds the controller tokens list. The controller tokens list is a list of all the controllers in the application.
   *
   * @param ReflectionAttribute[] $moduleTokensList The list of module tokens.
   * @return array<string, ReflectionClass> The controller tokens list.
   * @throws EntryNotFoundException
   */
  public function buildControllerTokensList(array $moduleTokensList): array
  {
    $this->controllerTokensList = [];
    $this->controllerPathTokenIdMap = [];
    $this->moduleControllerTokensMap = [];
    $this->moduleBranchPrefixMap = [];
    $this->controllerRouteMetadata = [];

    if (empty($moduleTokensList)) {
      return $this->getControllerTokenList();
    }

    $visitedModules = [];
    $this->buildModuleControllerTokens(
      moduleClass: $this->moduleManager->getRootModuleClass(),
      inheritedPrefix: '/',
      visitedModules: $visitedModules,
    );

    return $this->getControllerTokenList();
  }

  /**
   * Returns the controller reflection from the given token ID.
   *
   * @param string $tokenId The token ID of the controller.
   * @return ReflectionClass|null The controller reflection.
   * @throws EntryNotFoundException If the controller is not found.
   */
  private function getControllerReflection(string $tokenId): ?ReflectionClass
  {
    try {
      $reflectionClass = new ReflectionClass($tokenId);
      $this->lastLoadedAttributes = $reflectionClass->getAttributes(Controller::class);

      if (!$this->lastLoadedAttributes) {
        $this->lastLoadedAttributes = $reflectionClass->getAttributes(\Assegai\Attributes\Controller::class);
      }
      return (! empty($this->lastLoadedAttributes) ) ? $reflectionClass : null;
    } catch (ReflectionException) {
      throw new EntryNotFoundException($tokenId);
    }
  }

  /**
   * Builds the controller token metadata for the given module and its imported descendants.
   *
   * @param string $moduleClass
   * @param string $inheritedPrefix
   * @param array<string, bool> $visitedModules
   * @return void
   * @throws EntryNotFoundException
   */
  private function buildModuleControllerTokens(string $moduleClass, string $inheritedPrefix, array &$visitedModules): void
  {
    if (!isset($this->moduleManager->getModuleTokens()[$moduleClass]) || isset($visitedModules[$moduleClass])) {
      return;
    }

    $visitedModules[$moduleClass] = true;
    $moduleReflection = $this->moduleManager->getModuleTokens()[$moduleClass];

    /** @var array{controllers: string[]} $args */
    $args = $moduleReflection->getArguments();
    $controllers = $args['controllers'] ?? [];
    $moduleControllers = [];
    $moduleBranchPrefix = $this->normalizePath($inheritedPrefix);
    $isFirstController = true;

    foreach ($controllers as $tokenId) {
      if (!$controllerReflection = $this->getControllerReflection($tokenId)) {
        continue;
      }

      $localPath = $this->getControllerPath($controllerReflection);
      $resolvedPath = $this->combinePaths($inheritedPrefix, $localPath);

      $this->controllerTokensList[$tokenId] = $controllerReflection;
      $this->controllerPathTokenIdMap[$tokenId] = $resolvedPath;
      $this->controllerRouteMetadata[$tokenId] = [
        'module' => $moduleClass,
        'local_path' => $localPath,
        'resolved_path' => $resolvedPath,
        'hosts' => $this->getControllerHostsFromReflection($controllerReflection),
      ];

      $moduleControllers[$tokenId] = $controllerReflection;

      if ($isFirstController) {
        $moduleBranchPrefix = $resolvedPath;
        $isFirstController = false;
      }
    }

    $this->moduleControllerTokensMap[$moduleClass] = $moduleControllers;
    $this->moduleBranchPrefixMap[$moduleClass] = $moduleBranchPrefix;

    foreach ($this->moduleManager->getImportedModules($moduleClass) as $importedModuleClass) {
      $this->buildModuleControllerTokens(
        moduleClass: $importedModuleClass,
        inheritedPrefix: $moduleBranchPrefix,
        visitedModules: $visitedModules,
      );
    }
  }

  /**
   * Extracts the local controller path from the controller attribute.
   *
   * @param ReflectionClass $reflectionClass
   * @return string
   */
  private function getControllerPath(ReflectionClass $reflectionClass): string
  {
    $instance = $this->getControllerAttributeInstance($reflectionClass);

    if (is_null($instance)) {
      return '/';
    }

    return $this->normalizePath($instance->path ?? '/');
  }

  /**
   * Extracts the configured host patterns from the controller attribute.
   *
   * @param ReflectionClass $reflectionClass
   * @return array<int, string>
   */
  private function getControllerHostsFromReflection(ReflectionClass $reflectionClass): array
  {
    $instance = $this->getControllerAttributeInstance($reflectionClass);
    $hosts = $instance->host ?? null;

    if (is_null($hosts)) {
      return [];
    }

    if (is_string($hosts)) {
      return [trim($hosts)];
    }

    return array_values(array_filter(
      array_map(static fn(mixed $host): string => is_string($host) ? trim($host) : '', $hosts),
      static fn(string $host): bool => $host !== ''
    ));
  }

  /**
   * Returns the concrete controller attribute instance for the given reflection.
   *
   * @param ReflectionClass $reflectionClass
   * @return object|null
   */
  private function getControllerAttributeInstance(ReflectionClass $reflectionClass): ?object
  {
    $attributes = $reflectionClass->getAttributes(Controller::class);

    if (!$attributes) {
      $attributes = $reflectionClass->getAttributes(\Assegai\Attributes\Controller::class);
    }

    if (empty($attributes)) {
      return null;
    }

    return $attributes[0]->newInstance();
  }

  /**
   * Joins path fragments into a normalized absolute route path.
   *
   * @param string ...$paths
   * @return string
   */
  private function combinePaths(string ...$paths): string
  {
    $segments = [];

    foreach ($paths as $path) {
      $normalized = trim($path);

      if ($normalized === '' || $normalized === '/') {
        continue;
      }

      foreach (explode('/', trim($normalized, '/')) as $segment) {
        if ($segment === '') {
          continue;
        }

        $segments[] = $segment;
      }
    }

    return empty($segments) ? '/' : '/' . implode('/', $segments);
  }

  /**
   * Normalizes a route path to a leading-slash form.
   *
   * @param string $path
   * @return string
   */
  private function normalizePath(string $path): string
  {
    $trimmedPath = trim($path);

    if ($trimmedPath === '' || $trimmedPath === '/') {
      return '/';
    }

    return '/' . trim($trimmedPath, '/');
  }
}
