<?php /** @noinspection ALL */

namespace Assegai\Core;

use Assegai\Core\Config\ApplicationConfigLoader;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Exceptions\ConfigurationException;
use Assegai\Core\Util\Debug\Log;
use Assegai\Core\Util\Paths;
use Dotenv\Dotenv;
use Exception;

/**
 * The Config class provides methods for retrieving or setting configuration
 * values.
 */
class Config
{
  private const ERR_MSG_CONFIG_NOT_FOUND = 'Config Not Found: ';

  /**
   * Hydrate the environment variables and configuration options
   *
   * @param string|null $configDirectory The directory containing the config files
   *
   * @return void
   */
  public static function hydrate(?string $configDirectory = null): void
  {
    $workingDirectory = $configDirectory ?? Paths::getWorkingDirectory();
    $envPath = Paths::join(trim($workingDirectory), '.env');

    // Load .env file
    if (file_exists($envPath)) {
      $dotEnv = Dotenv::createImmutable($workingDirectory);
      $dotEnv->load();
    }

    $config = ApplicationConfigLoader::load(
      $workingDirectory,
      Config::environment() === EnvironmentType::PRODUCTION,
    );
    $_ENV = $_ENV + $config;

    if (!isset($GLOBALS['config'])) {
      $GLOBALS['config'] = $config;
    }
  }

  /**
   * @param string $name
   * @return mixed
   */
  public static function get(string $name, ?string $configPath = null): mixed
  {
    if (!isset($GLOBALS['config'])) {
      Config::hydrate($configPath);
    }

    return $_ENV[$name] ?? $GLOBALS['config'][$name] ?? NULL;
  }

  /**
   * Get database configs.
   * @param string $type The type of the database. DEFAULT: 'mysql'
   * @param string $name The name of the database
   * @param bool $associative When TRUE, returned objects will be converted into associative arrays.
   * @return array|object|null
   */
  public static function database(
    string $type,
    string $name,
    bool $associative = true,
    ?string $configPath = null
  ): array|object|null
  {
    $config = self::get(name: 'databases', configPath: $configPath)[$type][$name] ?? [];
    return $associative ? $config : (object)$config;
  }

  /**
   * @param string $name
   * @param mixed $value
   * @return void
   */
  public static function set(string $name, mixed $value): void
  {
    $GLOBALS['config'][$name] = $value;
  }

  /**
   * @param string $name
   * @return mixed
   */
  public static function getAsObject(string $name): ?object
  {
    $config = Config::get(name: $name);

    return is_array($config) ? (object)$config : $config;
  }

  /**
   * Determines the environment type based on the value of the `ENV` environment
   *
   * @return EnvironmentType|false Returns the environment type if it can be determined,
   */
  public static function environment(): EnvironmentType|false
  {
    $env = self::normalizeEnvironmentValue(
      self::environmentValue('ENV') ?? self::environmentValue('APP_ENV')
    );

    return match ($env) {
      'PROD', 'PRODUCTION' => EnvironmentType::PRODUCTION,
      'STAGING', 'STAGE' => EnvironmentType::STAGING,
      'LOCAL' => EnvironmentType::LOCAL,
      'QA' => EnvironmentType::QA,
      'DEV', 'DEVELOP', 'DEVELOPMENT' => EnvironmentType::DEVELOP,
      'TEST', 'TESTING' => EnvironmentType::TEST,
      default => false
    };
  }

  /**
   * @deprecated Use `Config::environment()` instead.
   * @param string $name
   * @param string $value
   * @return void
   */
  public static function setEnvironment(string $name, string $value): void
  {
    $_ENV[$name] = $value;
  }

  /**
   * Gets an environment configuration value from the workspace file, `assegai.json`.
   *
   * @param string $name The name of configuration value to be retrieved or set.
   *
   * @return mixed Returns the configuration value of given name if it exists,
   * or `NULL` if the `assegai.json` file or configuration doesn't exist.
   */
  public static function getWorkspaceConfig(string $name, ?string $workspaceDirectory = null): mixed
  {
    if (!$workspaceDirectory)
    {
      $workspaceDirectory = Paths::getWorkingDirectory();
    }

    $workspaceConfigFilename = Paths::join($workspaceDirectory, 'assegai.json');

    if (!file_exists($workspaceConfigFilename))
    {
      throw new Exception(self::ERR_MSG_CONFIG_NOT_FOUND . $workspaceConfigFilename);
    }

    $config = file_get_contents($workspaceConfigFilename);

    if (!empty($config) && str_starts_with($config, '{'))
    {
      $config = json_decode($config);
    }

    return $config->$name ?? null;
  }

  /**
   * Updates the workspace configuration file.
   *
   * @param string $value The new value to set the configuration to.
   *
   * @throws Exception
   */
  public static function updateWorkspaceConfig(string $name, mixed $value, ?string $workspaceDirectory = null): void
  {
    if (!$workspaceDirectory)
    {
      $workspaceDirectory = Paths::getWorkingDirectory();
    }

    $workspaceConfigFilename = Paths::join($workspaceDirectory, 'assegai.json');

    if (!file_exists($workspaceConfigFilename))
    {
      throw new Exception(self::ERR_MSG_CONFIG_NOT_FOUND . $workspaceConfigFilename);
    }

    $config = file_get_contents($workspaceConfigFilename);

    if (!empty($config) && json_is_valid($config))
    {
      $config = json_decode($config);
    }
    else
    {
      $config = json_decode(json_encode([]));
    }

    $config->$name = $value;

    $configContents = json_encode($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    $bytesWritten = file_put_contents($workspaceConfigFilename, $configContents);

    if (false === $bytesWritten)
    {
      throw new ConfigurationException("Failed to write to configuration file.");
    }

    Log::info('UPDATE', basename($workspaceConfigFilename) . " ($bytesWritten bytes)");
  }

  /**
   * @return bool
   */
  public static function isDebug(): bool
  {
    return filter_var(
      self::environmentValue('DEBUG_MODE') ?? self::environmentValue('APP_DEBUG') ?? false,
      FILTER_VALIDATE_BOOL
    );
  }

  /**
   * Reads an environment value from PHP's supported environment sources.
   */
  public static function environmentValue(string $name): mixed
  {
    if (array_key_exists($name, $_ENV)) {
      return $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER)) {
      return $_SERVER[$name];
    }

    $value = getenv($name);

    return $value === false ? null : $value;
  }

  private static function normalizeEnvironmentValue(mixed $value): ?string
  {
    if (!is_scalar($value)) {
      return null;
    }

    $normalized = trim((string)$value);
    $normalized = preg_replace('/\s+#.*$/', '', $normalized) ?? $normalized;

    return strtoupper(trim($normalized));
  }
}
