<?php /** @noinspection ALL */

namespace Assegai\Core;

use Assegai\Core\Enumerations\EnvironmentType;
use Exception;

/**
 * The Config class provides methods for retrieving or setting configuration
 * values.
 */
class Config
{
  /**
   * @return void
   */
  public static function hydrate(): void
  {
    $config = [];
    $workingDirectory = shell_exec('pwd');
    $configPath = trim($workingDirectory) . '/config/default.php';
    $envPath = trim($workingDirectory) . '/.env';

    if (file_exists($configPath))
    {
      $config = require($configPath);

      $_ENV = array_merge($_ENV, $config);
    }
    $configPath = str_replace('default', 'local', $configPath);
    if (file_exists($configPath))
    {
      $config = require($configPath);

      $_ENV = array_merge($_ENV, $config);
    }

    if (!isset($GLOBALS['config']))
    {
      $defaultConfigPath = 'config/default.php';
      $localConfigPath = 'config/local.php';
      $productionConfigPath = 'config/production.php';

      $config = file_exists($defaultConfigPath)
        ? require($defaultConfigPath)
        : [];

      if (Config::environment() === EnvironmentType::PRODUCTION && file_exists($productionConfigPath))
      {
        $productionConfig =
          file_exists($productionConfigPath)
            ? require($productionConfigPath)
            : [];
        $config = array_merge($config, $productionConfig);
      }

      if (file_exists($localConfigPath))
      {
        $localConfig =
          file_exists($localConfigPath)
            ? require($localConfigPath)
            : [];
        $config = array_merge($config, $localConfig);
      }

      $GLOBALS['config'] = $config;
    }
  }

  /**
   * @param string $name
   * @return mixed
   */
  public static function get(string $name): mixed
  {
    if (!isset($GLOBALS['config']))
    {
      Config::hydrate();
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
  public static function database(string $type, string $name, bool $associative = true): array|object|null
  {
    $config = self::get('databases')[$type][$name] ?? [];
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
  public static function asObject(string $name): mixed
  {
    $config = Config::get(name: $name);

    return is_array($config) ? json_decode(json_encode($config)) : $config;
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
  public static function workspace(string $name): mixed
  {
    if (!file_exists('assegai.json'))
    {
      return NULL;
    }
    $config = file_get_contents('assegai.json');

    if (!empty($config) && str_starts_with($config, '{'))
    {
      $config = json_decode($config);
    }

    return $config->$name ?? NULL;
  }

  /**
   * @param string $value The new value to set the configuration to.
   *
   * @throws Exception
   */
  public static function setWorkspace(string $name, mixed $value): void
  {
    if (!file_exists('assegai.json'))
    {
      throw new Exception('Missing workspace config file: assegai.json');
    }

    $config = file_get_contents('assegai.json');

    if (!empty($config) && str_starts_with($config, '{'))
    {
      $config = json_decode($config);
    }
    else
    {
      $config = json_decode(json_encode([]));
    }

    $config->$name = $value;
  }

  /**
   * @return bool
   */
  public static function isDebug(): bool
  {
    return filter_var(($_ENV['DEBUG_MODE'] ?? false), FILTER_VALIDATE_BOOL);
  }
}