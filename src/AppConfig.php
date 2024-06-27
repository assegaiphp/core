<?php

namespace Assegai\Core;

use Assegai\Core\Enumerations\Http\ContextType;
use Dotenv\Dotenv;
use RuntimeException;

/**
 * The app configuration.
 *
 * @package Assegai\Core
 */
readonly class AppConfig
{
  /**
   * @var array<string, mixed> The configuration.
   */
  protected array $config;

  /**
   * AppConfig constructor.
   *
   * @param ContextType $type The context type.
   */
  public function __construct(
    public ContextType $type = ContextType::HTTP
  )
  {
    $workingDirectory = getcwd() ?: '.';
    $dotenv = Dotenv::createImmutable($workingDirectory);
    $dotenv->safeLoad();

    $appConfigFilename = $workingDirectory . '/assegai.json';

    if (!file_exists($appConfigFilename))
    {
      throw new RuntimeException('The app configuration file does not exist.');
    }

    $this->config = json_decode(file_get_contents($appConfigFilename) ?: '', true);
  }

  /**
   * Gets a configuration value.
   *
   * @param string $path The path to the configuration value. Use dot notation e.g. 'database.host'.
   * @return mixed The configuration value.
   */
  public function get(string $path, mixed $default = null): mixed
  {
    $keys = explode('.', $path);
    $value = $this->config;

    foreach ($keys as $key)
    {
      if (!array_key_exists($key, $value))
      {
        return null;
      }

      $value = $value[$key];
    }

    return $value ?? $default;
  }
}