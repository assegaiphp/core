<?php /** @noinspection ALL */

namespace Assegai\Core;

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
    $config = [];
    $workingDirectory = $configDirectory ?? getcwd();
    $configFilename = Paths::join(trim($workingDirectory), 'config', 'default.php');
    $envPath = Paths::join(trim($workingDirectory), '.env');

    // Load .env file
    if (is_file($envPath))
    {
      $dotEnv = Dotenv::createImmutable($workingDirectory);
      $dotEnv->load();
    }

    // Load the default config file
    if (is_file($configFilename))
    {
      $config = require($configFilename);

      $_ENV = $_ENV + $config;
    }

    // Attempt to load the local file
    $configFilename = str_replace('default', 'local', $configFilename);
    if (is_file($configFilename))
    {
      $config = require($configFilename);

      $_ENV = array_replace_recursive($_ENV, $config);
    }

    if (!isset($GLOBALS['config']))
    {
      $defaultConfigFilename = Paths::join(trim($workingDirectory), 'config', 'default.php');
      $localConfigFilename = Paths::join(trim($workingDirectory), 'config', 'local.php');
      $productionConfigFilename = Paths::join(trim($workingDirectory), 'config', 'production.php');

      $config = is_file($defaultConfigFilename)
        ? require($defaultConfigFilename)
        : [];

      // If the environment is production, merge the production config with the default config
      if (Config::environment() === EnvironmentType::PRODUCTION && is_file($productionConfigFilename))
      {
        $productionConfig =
          is_file($productionConfigFilename)
            ? require($productionConfigFilename)
            : [];
        $config = array_replace_recursive($config, $productionConfig);
      }

      if (is_file($localConfigFilename))
      {
        $localConfig =
          is_file($localConfigFilename)
            ? require($localConfigFilename)
            : [];

        $config = array_replace_recursive($config, $localConfig);
      }

      $GLOBALS['config'] = $config;
    }
  }

  /**
   * @param string $name
   * @return mixed
   */
  public static function get(string $name, ?string $configPath = null): mixed
  {
    if (!isset($GLOBALS['config']))
    {
      Config::hydrate($configPath);
    }

    return $GLOBALS['config'][$name] ?? NULL;
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
   * Gets an environment configuration value from the workspace file, `.env`.
   *
   * @param string $name The name of configuration value to be returned.
   *
   * @return mixed Returns the current configuration value of the given name,
   * or `NULL` if it doesn't exist.
   */
  public static function environment(): EnvironmentType|false
  {
    $env = $_ENV['ENV'] ?? null;
    return match ($env) {
      'PROD' => EnvironmentType::PRODUCTION,
      'STAGING' => EnvironmentType::STAGING,
      'LOCAL' => EnvironmentType::LOCAL,
      'QA' => EnvironmentType::QA,
      'DEV' => EnvironmentType::DEVELOP,
      'TEST' => EnvironmentType::TEST,
      default => false
    };
  }

  /**
   * Sets the value of the pair identified by $name to $value, creating a new
   * key/value pair if none existed for $name previously in the workspace `.env` file.
   *
   * @param string $name The name of configuration value to be set.
   * @param string $value The new value to set the configuration to.
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
      $workspaceDirectory = getcwd();
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
      $workspaceDirectory = getcwd();
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
    return filter_var(($_ENV['DEBUG_MODE'] ?? false), FILTER_VALIDATE_BOOL);
  }
}