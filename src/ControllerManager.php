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
   * @var array<class-string, array<class-string, ReflectionClass<object>>> A map of controller reflections keyed by module class.
   */
  protected array $moduleControllerTokensMap = [];
  /**
   * @var array<class-string, string> A map of fully resolved module branch prefixes keyed by module class.
   */
  protected array $moduleBranchPrefixMap = [];
  /**
   * @var array<string, array<int, array<int, string>>> A map of inherited host constraint groups keyed by module class.
   */
  protected array $moduleBranchHostGroupsMap = [];
  /**
   * @var array<string, array{module: string, local_path: string, resolved_path: string, hosts: array<int, string>, host_groups: array<int, array<int, string>>}>
   */
  protected array $controllerRouteMetadata = [];
  /**
   * @var array<string, array{id: string, module: string, controller: ReflectionClass<object>, local_path: string, resolved_path: string, hosts: array<int, string>, host_groups: array<int, array<int, string>>}>
   */
  protected array $controllerRouteContexts = [];
  /**
   * @var array<class-string, array<string, true>>
   */
  protected array $controllerRouteContextIdsMap = [];
  /**
   * @var array<string, array{id: string, module: string, prefix: string, host_groups: array<int, array<int, string>>, import_host_groups: array<int, array<int, string>>, controllers: array<int, string>, imports: array<int, string>}>
   */
  protected array $moduleRouteContexts = [];
  protected ?string $rootModuleRouteContextId = null;
  protected int $graphVersion = 0;

  /**
   * ControllerManager constructor.
   */
  private final function __construct(?ModuleManager $moduleManager = null)
  {
    $this->moduleManager = $moduleManager ?? ModuleManager::getInstance();
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
   * Creates a fresh controller manager and promotes it to the active singleton for compatibility.
   *
   * @param ModuleManager|null $moduleManager
   * @return ControllerManager
   */
  public static function createFresh(?ModuleManager $moduleManager = null): ControllerManager
  {
    self::$instance = new ControllerManager($moduleManager);
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
   * Returns the precomputed host constraint groups inherited by the given module branch.
   *
   * @param string $moduleClass
   * @return array<int, array<int, string>>
   */
  public function getModuleBranchHostGroups(string $moduleClass): array
  {
    return $this->moduleBranchHostGroupsMap[$moduleClass] ?? [];
  }

  /**
   * Returns the owning module class for the given controller.
   *
   * @param string $controllerClass
   * @return string|null
   */
  public function getOwningModule(string $controllerClass): ?string
  {
    return $this->controllerRouteMetadata[$controllerClass]['module'] ?? null;
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
   * Returns the precomputed effective host constraint groups for the given controller.
   *
   * @param string $controllerClass
   * @return array<int, array<int, string>>
   */
  public function getControllerHostGroups(string $controllerClass): array
  {
    return $this->controllerRouteMetadata[$controllerClass]['host_groups'] ?? [];
  }

  /**
   * Returns every distinct mount context for the given controller, or for the whole application.
   *
   * @param string|null $controllerClass
   * @return array<string, array{id: string, module: string, controller: ReflectionClass<object>, local_path: string, resolved_path: string, hosts: array<int, string>, host_groups: array<int, array<int, string>>}>
   */
  public function getControllerRouteContexts(?string $controllerClass = null): array
  {
    if (is_null($controllerClass)) {
      return $this->controllerRouteContexts;
    }

    $contexts = [];

    foreach (array_keys($this->controllerRouteContextIdsMap[$controllerClass] ?? []) as $contextId) {
      if (isset($this->controllerRouteContexts[$contextId])) {
        $contexts[$contextId] = $this->controllerRouteContexts[$contextId];
      }
    }

    return $contexts;
  }

  /**
   * @return array{id: string, module: string, controller: ReflectionClass<object>, local_path: string, resolved_path: string, hosts: array<int, string>, host_groups: array<int, array<int, string>>}|null
   */
  public function getControllerRouteContext(string $contextId): ?array
  {
    return $this->controllerRouteContexts[$contextId] ?? null;
  }

  /**
   * @return array{id: string, module: string, prefix: string, host_groups: array<int, array<int, string>>, import_host_groups: array<int, array<int, string>>, controllers: array<int, string>, imports: array<int, string>}|null
   */
  public function getModuleRouteContext(string $contextId): ?array
  {
    return $this->moduleRouteContexts[$contextId] ?? null;
  }

  public function getRootModuleRouteContextId(): ?string
  {
    return $this->rootModuleRouteContextId;
  }

  /**
   * Returns a monotonic version that changes whenever route metadata is rebuilt.
   */
  public function getGraphVersion(): int
  {
    return $this->graphVersion;
  }

  /**
   * Returns every distinct resolved path at which the controller is mounted.
   *
   * @param string $controllerClass
   * @return array<int, string>
   */
  public function getResolvedControllerPaths(string $controllerClass): array
  {
    $paths = [];

    foreach ($this->getControllerRouteContexts($controllerClass) as $context) {
      $paths[$context['resolved_path']] = true;
    }

    return array_keys($paths);
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
    $this->graphVersion++;
    $this->controllerTokensList = [];
    $this->controllerPathTokenIdMap = [];
    $this->moduleControllerTokensMap = [];
    $this->moduleBranchPrefixMap = [];
    $this->moduleBranchHostGroupsMap = [];
    $this->controllerRouteMetadata = [];
    $this->controllerRouteContexts = [];
    $this->controllerRouteContextIdsMap = [];
    $this->moduleRouteContexts = [];
    $this->rootModuleRouteContextId = null;

    if (empty($moduleTokensList)) {
      return $this->getControllerTokenList();
    }

    /** @var class-string $rootModuleClass */
    $rootModuleClass = $this->moduleManager->getRootModuleClass();
    $this->buildRouteContexts($rootModuleClass);

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
   * Builds a deduplicated graph of module and controller mount contexts.
   *
   * A module class may appear in more than one context when distinct parents contribute different
   * path or host constraints. Identical diamond mounts collapse to one context. Within cyclic
   * components, path-local ancestry permits every finite simple mount without following a module
   * twice on the same branch.
   *
   * @param class-string $rootModuleClass
   * @return void
   * @throws EntryNotFoundException
   */
  private function buildRouteContexts(string $rootModuleClass): void
  {
    $moduleTokens = $this->moduleManager->getModuleTokens();

    if (!isset($moduleTokens[$rootModuleClass])) {
      return;
    }

    /** @var array<class-string, array<int, class-string>> $importsMap */
    $importsMap = [];

    foreach (array_keys($moduleTokens) as $moduleClass) {
      $importsMap[$moduleClass] = array_values(array_filter(
        $this->moduleManager->getImportedModules($moduleClass),
        static fn(string $importedModuleClass): bool => isset($moduleTokens[$importedModuleClass]),
      ));
    }

    $moduleComponentMap = $this->buildModuleComponentMap($rootModuleClass, $importsMap);
    $rootContextId = $this->buildModuleRouteContextId($rootModuleClass, '/', []);
    $this->rootModuleRouteContextId = $rootContextId;
    $pendingContexts = [[
      'id' => $rootContextId,
      'module' => $rootModuleClass,
      'inherited_prefix' => '/',
      'inherited_host_groups' => [],
      'component_ancestry' => [$rootModuleClass => true],
    ]];
    $expandedStates = [];

    while ($pendingContext = array_pop($pendingContexts)) {
      $contextId = $pendingContext['id'];
      $componentAncestry = $pendingContext['component_ancestry'];
      $ancestryModules = array_keys($componentAncestry);
      sort($ancestryModules);
      $expansionStateId = hash('sha256', $contextId . "\0" . implode("\0", $ancestryModules));

      if (isset($expandedStates[$expansionStateId])) {
        continue;
      }

      $expandedStates[$expansionStateId] = true;
      $moduleClass = $pendingContext['module'];
      $inheritedPrefix = $pendingContext['inherited_prefix'];
      $inheritedHostGroups = $pendingContext['inherited_host_groups'];

      if (!isset($this->moduleRouteContexts[$contextId])) {
        $moduleControllers = $this->loadModuleControllers($moduleClass);
        $controllerContextIds = [];
        $moduleBranchPrefix = $this->normalizePath($inheritedPrefix);
        $moduleBranchHostGroups = $inheritedHostGroups;
        $isFirstController = true;

        foreach ($moduleControllers as $tokenId => $controllerReflection) {
          $localPath = $this->getControllerPath($controllerReflection);
          $localHosts = $this->getControllerHostsFromReflection($controllerReflection);
          $resolvedPath = $this->combinePaths($inheritedPrefix, $localPath);
          $effectiveHostGroups = $this->mergeHostGroups($inheritedHostGroups, $localHosts);
          $controllerContextId = $this->buildControllerRouteContextId(
            $contextId,
            $tokenId,
            $resolvedPath,
            $effectiveHostGroups,
          );

          $this->controllerRouteContexts[$controllerContextId] = [
            'id' => $controllerContextId,
            'module' => $moduleClass,
            'controller' => $controllerReflection,
            'local_path' => $localPath,
            'resolved_path' => $resolvedPath,
            'hosts' => $localHosts,
            'host_groups' => $effectiveHostGroups,
          ];
          $this->controllerRouteContextIdsMap[$tokenId][$controllerContextId] = true;
          $controllerContextIds[] = $controllerContextId;

          $this->controllerPathTokenIdMap[$tokenId] ??= $resolvedPath;
          $this->controllerRouteMetadata[$tokenId] ??= [
            'module' => $moduleClass,
            'local_path' => $localPath,
            'resolved_path' => $resolvedPath,
            'hosts' => $localHosts,
            'host_groups' => $effectiveHostGroups,
          ];

          if ($isFirstController) {
            $moduleBranchPrefix = $resolvedPath;

            if (!empty($localHosts)) {
              $moduleBranchHostGroups = $effectiveHostGroups;
            }

            $isFirstController = false;
          }
        }

        $this->moduleBranchPrefixMap[$moduleClass] ??= $moduleBranchPrefix;
        $this->moduleBranchHostGroupsMap[$moduleClass] ??= $inheritedHostGroups;
        $this->moduleRouteContexts[$contextId] = [
          'id' => $contextId,
          'module' => $moduleClass,
          'prefix' => $moduleBranchPrefix,
          'host_groups' => $inheritedHostGroups,
          'import_host_groups' => $moduleBranchHostGroups,
          'controllers' => $controllerContextIds,
          'imports' => [],
        ];
      }

      $moduleContext = $this->moduleRouteContexts[$contextId];

      foreach ($importsMap[$moduleClass] ?? [] as $importedModuleClass) {
        if (isset($componentAncestry[$importedModuleClass])) {
          continue;
        }

        $importContextId = $this->buildModuleRouteContextId(
          $importedModuleClass,
          $moduleContext['prefix'],
          $moduleContext['import_host_groups'],
        );

        if (!in_array($importContextId, $this->moduleRouteContexts[$contextId]['imports'], true)) {
          $this->moduleRouteContexts[$contextId]['imports'][] = $importContextId;
        }

        $importAncestry = ($moduleComponentMap[$importedModuleClass] ?? null) === ($moduleComponentMap[$moduleClass] ?? null)
          ? [...$componentAncestry, $importedModuleClass => true]
          : [$importedModuleClass => true];
        $pendingContexts[] = [
          'id' => $importContextId,
          'module' => $importedModuleClass,
          'inherited_prefix' => $moduleContext['prefix'],
          'inherited_host_groups' => $moduleContext['import_host_groups'],
          'component_ancestry' => $importAncestry,
        ];
      }
    }
  }

  /**
   * Loads and caches controller definitions declared by a module.
   *
   * @param class-string $moduleClass
   * @return array<class-string, ReflectionClass<object>>
   * @throws EntryNotFoundException
   */
  private function loadModuleControllers(string $moduleClass): array
  {
    if (array_key_exists($moduleClass, $this->moduleControllerTokensMap)) {
      return $this->moduleControllerTokensMap[$moduleClass];
    }

    $moduleReflection = $this->moduleManager->getModuleTokens()[$moduleClass] ?? null;

    if (is_null($moduleReflection)) {
      return $this->moduleControllerTokensMap[$moduleClass] = [];
    }

    /** @var array{controllers?: class-string[]} $args */
    $args = $moduleReflection->getArguments();
    $moduleControllers = [];

    foreach ($args['controllers'] ?? [] as $tokenId) {
      if (!$controllerReflection = $this->getControllerReflection($tokenId)) {
        continue;
      }

      $this->controllerTokensList[$tokenId] = $controllerReflection;
      $moduleControllers[$tokenId] = $controllerReflection;
    }

    return $this->moduleControllerTokensMap[$moduleClass] = $moduleControllers;
  }

  /**
   * Assigns each reachable module to a strongly connected component using iterative Kosaraju passes.
   * Route expansion only needs ancestry tracking inside these cyclic components; the component graph
   * itself is acyclic and can therefore reuse identical mount contexts without repeated work.
   *
   * @param class-string $rootModuleClass
   * @param array<class-string, array<int, class-string>> $importsMap
   * @return array<class-string, int>
   */
  private function buildModuleComponentMap(string $rootModuleClass, array $importsMap): array
  {
    $visited = [$rootModuleClass => true];
    $finishOrder = [];
    $stack = [[
      'module' => $rootModuleClass,
      'index' => 0,
    ]];

    while (!empty($stack)) {
      $stackIndex = array_key_last($stack);
      $frame = $stack[$stackIndex];
      $moduleImports = $importsMap[$frame['module']] ?? [];

      if ($frame['index'] >= count($moduleImports)) {
        $finishOrder[] = $frame['module'];
        array_pop($stack);
        continue;
      }

      $importedModuleClass = $moduleImports[$frame['index']];
      $stack[$stackIndex]['index']++;

      if (isset($visited[$importedModuleClass])) {
        continue;
      }

      $visited[$importedModuleClass] = true;
      $stack[] = [
        'module' => $importedModuleClass,
        'index' => 0,
      ];
    }

    $reverseImportsMap = [];

    foreach (array_keys($visited) as $moduleClass) {
      $reverseImportsMap[$moduleClass] = [];
    }

    foreach (array_keys($visited) as $moduleClass) {
      foreach ($importsMap[$moduleClass] ?? [] as $importedModuleClass) {
        if (isset($visited[$importedModuleClass])) {
          $reverseImportsMap[$importedModuleClass][] = $moduleClass;
        }
      }
    }

    $componentMap = [];
    $componentId = 0;

    foreach (array_reverse($finishOrder) as $moduleClass) {
      if (isset($componentMap[$moduleClass])) {
        continue;
      }

      $componentMap[$moduleClass] = $componentId;
      $componentStack = [$moduleClass];

      while ($componentModule = array_pop($componentStack)) {
        foreach ($reverseImportsMap[$componentModule] ?? [] as $parentModuleClass) {
          if (isset($componentMap[$parentModuleClass])) {
            continue;
          }

          $componentMap[$parentModuleClass] = $componentId;
          $componentStack[] = $parentModuleClass;
        }
      }

      $componentId++;
    }

    /** @var array<class-string, int> $componentMap */
    return $componentMap;
  }

  /**
   * @param class-string $moduleClass
   * @param string $inheritedPrefix
   * @param array<int, array<int, string>> $inheritedHostGroups
   */
  private function buildModuleRouteContextId(string $moduleClass, string $inheritedPrefix, array $inheritedHostGroups): string
  {
    return hash('sha256', implode("\0", [
      $moduleClass,
      $this->normalizePath($inheritedPrefix),
      $this->buildHostGroupsKey($inheritedHostGroups),
    ]));
  }

  /**
   * @param string $moduleContextId
   * @param string $controllerClass
   * @param string $resolvedPath
   * @param array<int, array<int, string>> $hostGroups
   */
  private function buildControllerRouteContextId(
    string $moduleContextId,
    string $controllerClass,
    string $resolvedPath,
    array $hostGroups,
  ): string
  {
    return hash('sha256', implode("\0", [
      $moduleContextId,
      $controllerClass,
      $resolvedPath,
      $this->buildHostGroupsKey($hostGroups),
    ]));
  }

  /**
   * @param array<int, array<int, string>> $hostGroups
   */
  private function buildHostGroupsKey(array $hostGroups): string
  {
    return json_encode($hostGroups, JSON_UNESCAPED_SLASHES) ?: '';
  }

  /**
   * Merges inherited branch host constraints with local controller host alternatives.
   *
   * @param array<int, array<int, string>> $inheritedHostGroups
   * @param array<int, string> $localHosts
   * @return array<int, array<int, string>>
   */
  private function mergeHostGroups(array $inheritedHostGroups, array $localHosts): array
  {
    $localHosts = array_values(array_unique(array_filter($localHosts, static fn(string $host): bool => $host !== '')));

    if (empty($localHosts)) {
      return $this->dedupeHostGroups($inheritedHostGroups);
    }

    if (empty($inheritedHostGroups)) {
      return array_map(static fn(string $host): array => [$host], $localHosts);
    }

    $mergedGroups = [];

    foreach ($inheritedHostGroups as $hostGroup) {
      foreach ($localHosts as $localHost) {
        $mergedGroups[] = array_values(array_unique([...$hostGroup, $localHost]));
      }
    }

    return $this->dedupeHostGroups($mergedGroups);
  }

  /**
   * @param array<int, array<int, string>> $hostGroups
   * @return array<int, array<int, string>>
   */
  private function dedupeHostGroups(array $hostGroups): array
  {
    $uniqueGroups = [];
    $seen = [];

    foreach ($hostGroups as $hostGroup) {
      $hostGroup = array_values(array_unique(array_filter($hostGroup, static fn(string $host): bool => $host !== '')));

      if (empty($hostGroup)) {
        continue;
      }

      $key = implode("\n", $hostGroup);

      if (isset($seen[$key])) {
        continue;
      }

      $seen[$key] = true;
      $uniqueGroups[] = $hostGroup;
    }

    return $uniqueGroups;
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
