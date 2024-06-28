<?php

namespace Assegai\Core\Config;

use Assegai\Core\Interfaces\ConfigInterface;
use RuntimeException;

/**
 * The abstract configuration.
 *
 * @package Assegai\Core\Config
 */
abstract class AbstractConfig implements ConfigInterface
{
  /**
   * @var array<string, mixed> The configuration.
   */
  protected array $config = [];

  /**
   * AbstractConfig constructor.
   */
  public function __construct()
  {
    $this->load();
  }

  /**
   * @inheritDoc
   */
  public function load(): void
  {
    $configFilename = $this->getConfigFilename();

    if (!file_exists($configFilename))
    {
      throw new RuntimeException('The configuration file does not exist.');
    }

    $configFileContents = file_get_contents($configFilename) ?: '';

    if (json_is_valid($configFileContents)) {
      $this->config = json_decode($configFileContents, true);
    } else {
      $this->config = require($configFilename);
    }
  }

  /**
   * @inheritDoc
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

  /**
   * @inheritDoc
   */
  public function has(string $path): bool
  {
    return $this->get($path) !== null;
  }

  /**
   * @inheritDoc
   */
  public function set(string $path, mixed $value): void
  {
    if (! $this->has($path) ) {
      return;
    }

    $keys = explode('.', $path);
    $config = &$this->config;

    foreach ($keys as $key)
    {
      if (!array_key_exists($key, $config))
      {
        $config[$key] = [];
      }

      $config = &$config[$key];
    }

    $config = $value;
  }

  /**
   * @inheritDoc
   */
  public function remove(string $path): void
  {
    if ($this->has($path)) {
      $keys = explode('.', $path);
      $value = &$this->config;

      foreach ($keys as $key) {
        if (!array_key_exists($key, $value)) {
          return;
        }

        $value = &$value[$key];
      }

      unset($value);
    }
  }

  /**
   * @inheritDoc
   */
  public function commit(): int
  {
    if (! file_exists($this->getConfigFilename())) {
      throw new RuntimeException('The configuration file does not exist.');
    }

    $data = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (false === $data) {
      throw new RuntimeException('The configuration could not be encoded.');
    }

    $bytes = file_put_contents($this->getConfigFilename(), $data);

    if (false === $bytes) {
      throw new RuntimeException('The configuration could not be written.');
    }

    return $bytes;
  }

  /**
   * @inheritDoc
   */
  public function getWorkingDirectory(): string
  {
    return getcwd() ?: '.';
  }
}