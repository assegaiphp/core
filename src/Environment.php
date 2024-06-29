<?php

namespace Assegai\Core;

use Assegai\Core\Enumerations\EnvironmentType;

/**
 * The Environment class provides methods for determining the current environment.
 *
 * @package Assegai\Core
 */
class Environment
{
  /**
   * The constructor is private to prevent instantiation of this class.
   */
  private final function __construct()
  {
  }

  /**
   * Determines if the current environment is a debugging environment.
   *
   * @return bool True if the environment is a debugging environment, false otherwise.
   */
  public static function isDebug(): bool
  {
    return filter_var(($_ENV['DEBUG_MODE'] ?? false), FILTER_VALIDATE_BOOL);
  }

  /**
   * Determines if the current environment is production.
   *
   * @return bool True if the environment is production, false otherwise.
   */
  public static function isProduction(): bool
  {
    return Config::environment() === EnvironmentType::PRODUCTION;
  }

  /**
   * Determines if the current environment is the staging environment.
   *
   * @return bool True if the environment is the staging environment, false otherwise.
   */
  public static function isStaging(): bool
  {
    return Config::environment() === EnvironmentType::STAGING;
  }

  /**
   * Determines if the current environment is a local environment.
   *
   * @return bool True if the environment is a local environment, false otherwise.
   */
  public static function isLocal(): bool
  {
    return Config::environment() === EnvironmentType::LOCAL;
  }

  /**
   * Determines if the current environment is the QA environment.
   *
   * @return bool True if the environment is the QA environment, false otherwise.
   */
  public static function isQA(): bool
  {
    return Config::environment() === EnvironmentType::QA;
  }

  /**
   * Determines if the current environment is a Dev environment.
   *
   * @return bool True if the environment is a Dev environment, false otherwise.
   */
  public static function isDev(): bool
  {
    return Config::environment() === EnvironmentType::DEVELOP;
  }

  /**
   * Determines if the current environment is a testing environment.
   *
   * @return bool True if the environment is a testing environment, false otherwise.
   */
  public static function isTest(): bool
  {
    return Config::environment() === EnvironmentType::TEST;
  }
}