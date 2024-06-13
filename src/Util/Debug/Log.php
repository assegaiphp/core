<?php

namespace Assegai\Core\Util\Debug;

use Assegai\Core\Util\Debug\Console\Enumerations\Color;
use RuntimeException;

defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'wb'));
defined('STDERR') or define('STDERR', fopen('php://stderr', 'wb'));
defined('DEFAULT_LOG_FILE') or define('DEFAULT_LOG_FILE', getcwd() . '/logs/assegai.log');
defined('DEFAULT_ERROR_LOG') or define('DEFAULT_ERROR_LOG', getcwd() . '/logs/assegai.log');

/**
 * Class Log
 * @package Assegai\Core\Util\Debug
 */
class Log
{
  /**
   * Initializes the log directory.
   *
   * @throws RuntimeException
   */
  public static function init(): void
  {
    $logDir = dirname(DEFAULT_LOG_FILE);
    $errorDir = dirname(DEFAULT_ERROR_LOG);

    if (!file_exists($logDir))
    {
      if (! mkdir($logDir, 0777, true))
      {
        throw new RuntimeException('Failed to create the log directory.');
      }
    }

    if (! file_exists(DEFAULT_LOG_FILE))
    {
      if (! touch(DEFAULT_LOG_FILE))
      {
        throw new RuntimeException('Failed to create the log file.');
      }
    }

    if (!file_exists($errorDir))
    {
      if (! mkdir($errorDir, 0777, true))
      {
        throw new RuntimeException('Failed to create the log directory.');
      }
    }

    if (! file_exists(DEFAULT_ERROR_LOG))
    {
      if (! touch(DEFAULT_ERROR_LOG))
      {
        throw new RuntimeException('Failed to create the log file.');
      }
    }
  }

  public static function sprint(string $tag, string $message, bool $outputHTML = false, Color $color = Color::BLUE): string
  {
    $output = sprintf("<h3 style='color: %s'>%s: </h3><p>%s</p>\n" . PHP_EOL, $color->name(), $tag, $message);
    return $outputHTML ? $output : strip_tags($output);
  }

  public static function print(string $tag, string $message, bool $outputHTML = false, Color $color = Color::BLUE): void
  {
    echo self::sprint($tag, $message, $outputHTML, $color);
  }

  /**
   * Logs a debug message.
   *
   * @param string $tag
   * @param string $message
   */
  public static function debug(string $tag, string $message): void
  {
    error_log(
      printf("%sD/%s:\n%s%s\n" . PHP_EOL, Color::BLUE->value, $tag, Color::RESET->value, $message),
      3,
      DEFAULT_LOG_FILE
    );
  }

  /**
   * Logs an info message.
   *
   * @param string $tag
   * @param string $message
   */
  public static function info(string $tag, string $message): void
  {
    error_log(
      printf("%sI/%s:\n%s%s\n" . PHP_EOL, Color::BLUE->value, $tag, Color::RESET->value, $message),
      3,
      DEFAULT_LOG_FILE
    );
  }

  /**
   * Logs a warning message.
   *
   * @param string $tag
   * @param string $message
   */
  public static function warn(string $tag, string $message): void
  {
    error_log(
      printf("%sW/%s:\n%s%s\n" . PHP_EOL, Color::BLUE->value, $tag, Color::RESET->value, $message),
      3,
      DEFAULT_LOG_FILE
    );
  }

  /**
   * Logs an error message.
   *
   * @param string $tag
   * @param string $message
   * @param int $type
   * @param string|null $destination
   * @param string|null $headers
   */
  public static function error(string $tag, string $message, int $type = 3, ?string $destination = DEFAULT_ERROR_LOG, ?string $headers = null): void
  {
    error_log(
      sprintf("%sE/%s:\n%s%s\n" . PHP_EOL, Color::RED->value, $tag, Color::RESET->value, $message),
      $type,
      $destination,
      $headers
    );
  }
}