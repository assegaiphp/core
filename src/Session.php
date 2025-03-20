<?php

namespace Assegai\Core;

use Assegai\Core\Interfaces\ConfigInterface;
use Assegai\Core\Interfaces\SingletonInterface;

/**
 * Class Session
 *
 * @package Assegai\Core
 */
class Session implements SingletonInterface, ConfigInterface
{
  /**
   * The key for the last request time.
   */
  private const string KEY_LAST_REQUEST_TIME = 'LAST_REQUEST_TIME';

  /**
   * @var Session|null The instance of the class.
   */
  protected static ?Session $instance = null;

  /**
   * Session constructor.
   */
  private function __construct()
  {
  }

  /**
   * @inheritDoc
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Set a session value.
   *
   * @param string $path The path to the value.
   * @param mixed|null $default The default value to return if the value is not found.
   * @return mixed The value at the given path or the default value.
   */
  public function get(string $path, mixed $default = null): mixed
  {
    $value = $_SESSION;
    $keys = explode('.', $path);

    foreach ($keys as $key) {
      if (is_array($value) && array_key_exists($key, $value)) {
        $value = $value[$key];
      }
    }

    return $value ?? $default;
  }

  /**
   * Set a session value.
   *
   * @param string $path The path to the value.
   * @param mixed $value The value to set.
   */
  public function set(string $path, mixed $value): void
  {
    $keys = explode('.', $path);
    $session = &$_SESSION;

    foreach ($keys as $key) {
      if (! array_key_exists($key, $session)) {
        $session[$key] = [];
      }

      $session = &$session[$key];
    }

    $session = $value;
  }

  public function update(): void
  {
    // Do nothing
  }

  public function load(): void
  {
    // Do nothing
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
  public function remove(string $path): void
  {
    if ($this->has($path)) {
      $keys = explode('.', $path);
      $session = &$_SESSION;

      foreach ($keys as $key) {
        if (! array_key_exists($key, $session)) {
          return;
        }

        $session = &$session[$key];
      }

      unset($session);
    }
  }

  /**
   * @inheritDoc
   */
  public function commit(): int
  {
    return 0;
  }

  /**
   * @inheritDoc
   */
  public function getConfigFilename(): string
  {
    return '';
  }

  /**
   * @inheritDoc
   */
  public function getWorkingDirectory(): string
  {
    return '';
  }
}