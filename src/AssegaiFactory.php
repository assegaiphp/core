<?php

namespace Assegai\Core;

use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\Runtimes\OpenSwooleHttpRuntime;
use Assegai\Core\Runtimes\PhpHttpRuntime;
use Assegai\Core\Routing\Router;
use InvalidArgumentException;

final class AssegaiFactory
{
  private final function __construct()
  {
  }

  /**
   * @param string $moduleName
   * @param HttpRuntimeInterface|null $runtime
   * @return App
   */
  public static function create(string $moduleName, ?HttpRuntimeInterface $runtime = null): App
  {
    $injector = Injector::createFresh();
    $moduleManager = ModuleManager::createFresh($injector);
    $controllerManager = ControllerManager::createFresh($moduleManager);
    $router = Router::createFresh($injector, $controllerManager, $moduleManager);

    return new App(
      rootModuleClass: $moduleName,
      router: $router,
      controllerManager: $controllerManager,
      moduleManager: $moduleManager,
      injector: $injector,
      runtime: $runtime ?? self::resolveRuntimeFromEnvironment() ?? new PhpHttpRuntime(),
    );
  }

  /**
   * Creates an app using the runtime configuration from the current project.
   *
   * Environment overrides take precedence so CLI tools can steer older bootstraps
   * without having to rewrite the application code path.
   */
  public static function createFromProject(string $moduleName, ?string $workingDirectory = null, ?string $runtime = null): App
  {
    $workingDirectory ??= getcwd() ?: '.';
    $config = self::loadProjectConfig($workingDirectory);
    $configuredRuntime = $runtime
      ?? self::readEnvironmentValue('ASSEGAI_RUNTIME')
      ?? self::getConfigValue($config, 'development.server.runtime')
      ?? 'php';

    $runtimeOptions = [
      'host' => self::readEnvironmentValue('ASSEGAI_HOST')
        ?? self::getConfigValue($config, 'development.server.host')
        ?? 'localhost',
      'port' => (int) (
        self::readEnvironmentValue('ASSEGAI_PORT')
        ?? self::getConfigValue($config, 'development.server.port')
        ?? 5000
      ),
      'settings' => is_array(self::getConfigValue($config, 'development.server.openswoole'))
        ? self::getConfigValue($config, 'development.server.openswoole')
        : [],
    ];

    return self::create($moduleName, self::resolveRuntime($configuredRuntime, $runtimeOptions));
  }

  /**
   * Creates an app using a named runtime.
   *
   * @param string $moduleName
   * @param string $runtime
   * @return App
   */
  public static function createWithRuntime(string $moduleName, string $runtime, array $options = []): App
  {
    return self::create($moduleName, self::resolveRuntime($runtime, $options));
  }

  /**
   * Resolves a named runtime into a runtime instance.
   *
   * @param string $runtime
   * @return HttpRuntimeInterface
   */
  public static function resolveRuntime(string $runtime, array $options = []): HttpRuntimeInterface
  {
    return match (strtolower(trim($runtime))) {
      'php' => new PhpHttpRuntime(),
      'openswoole', 'swoole' => new OpenSwooleHttpRuntime(
        host: (string) ($options['host'] ?? '127.0.0.1'),
        port: (int) ($options['port'] ?? 9501),
        settings: is_array($options['settings'] ?? null) ? $options['settings'] : [],
      ),
      default => throw new InvalidArgumentException("Unsupported runtime [$runtime]."),
    };
  }

  private static function resolveRuntimeFromEnvironment(): ?HttpRuntimeInterface
  {
    $runtime = self::readEnvironmentValue('ASSEGAI_RUNTIME');

    if ($runtime === null || trim($runtime) === '') {
      return null;
    }

    return self::resolveRuntime($runtime, [
      'host' => self::readEnvironmentValue('ASSEGAI_HOST') ?? 'localhost',
      'port' => (int) (self::readEnvironmentValue('ASSEGAI_PORT') ?? 5000),
    ]);
  }

  /**
   * @return array<string, mixed>
   */
  private static function loadProjectConfig(string $workingDirectory): array
  {
    $filename = rtrim($workingDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'assegai.json';

    if (!is_file($filename)) {
      return [];
    }

    $decoded = json_decode(file_get_contents($filename) ?: '', true);

    return is_array($decoded) ? $decoded : [];
  }

  private static function getConfigValue(array $config, string $path): mixed
  {
    $tokens = explode('.', $path);
    $value = $config;

    foreach ($tokens as $token) {
      if (!is_array($value) || !array_key_exists($token, $value)) {
        return null;
      }

      $value = $value[$token];
    }

    return $value;
  }

  private static function readEnvironmentValue(string $key): ?string
  {
    $value = getenv($key);

    if ($value === false || $value === null || $value === '') {
      return null;
    }

    return (string) $value;
  }
}
