<?php

namespace Assegai\Core\Interfaces;

/**
 * Interface ConfigInterface
 *
 * @package Assegai\Core\Interfaces
 */
interface ConfigInterface
{
  /**
   * Load the configuration.
   */
  public function load(): void;

  /**
   * Get a configuration value.
   *
   * @param string $path The path to the value.
   * @param mixed|null $default The default value to return if the value is not found.
   * @return mixed The value at the given path or the default value.
   */
  public function get(string $path, mixed $default = null): mixed;

  /**
   * Set a configuration value.
   *
   * @param string $path The path to the value.
   * @param mixed $value The value to set.
   */
  public function set(string $path, mixed $value): void;

  /**
   * Check if a configuration value exists.
   *
   * @param string $path The path to the value.
   * @return bool True if the value exists, false otherwise.
   */
  public function has(string $path): bool;

  /**
   * Remove a configuration value.
   *
   * @param string $path The path to the value.
   */
  public function remove(string $path): void;

  /**
   * Save the configuration.
   *
   * @return int The number of bytes written to the configuration file.
   */
  public function commit(): int;

  /**
   * Get the configuration filename.
   *
   * @return string The configuration filename.
   */
  public function getConfigFilename(): string;

  /**
   * Get the working directory.
   *
   * @return string The working directory.
   */
  public function getWorkingDirectory(): string;
}