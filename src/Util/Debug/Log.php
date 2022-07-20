<?php

namespace Assegai\Core\Util\Debug;

use Assegai\Core\Util\Debug\Console\Enumerations\Color;

defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'wb'));
defined('STDERR') or define('STDERR', fopen('php://stderr', 'wb'));

class Log
{
  public static function sprint(string $tag, string $message, bool $outputHTML = false, Color $color = Color::BLUE): string
  {
    $output = sprintf("<h3 style='color: %s'>%s: </h3><p>%s</p>\n" . PHP_EOL, $color->name(), $tag, $message);
    return $outputHTML ? $output : strip_tags($output);
  }

  public static function print(string $tag, string $message, bool $outputHTML = false, Color $color = Color::BLUE): void
  {
    echo self::sprint($tag, $message, $outputHTML, $color);
  }

  public static function debug(string $tag, string $message): void
  {
    printf(STDOUT, "%sD/%s:\n%s%s\n" . PHP_EOL, Color::BLUE->value, $tag, Color::RESET->value, $message);
  }

  public static function info(string $tag, string $message): void
  {
    printf(STDOUT, "%sI/%s:\n%s%s\n" . PHP_EOL, Color::BLUE->value, $tag, Color::RESET->value, $message);
  }

  public static function warn(string $tag, string $message): void
  {
    printf(STDOUT, "%sW/%s:\n%s%s\n" . PHP_EOL, Color::BLUE->value, $tag, Color::RESET->value, $message);
  }

  public static function error(string $tag, string $message, int $type = 0, ?string $destination = null, ?string $headers = null): void
  {
    error_log(sprintf("%sE/%s:\n%s%s\n" . PHP_EOL, Color::RED->value, $tag, Color::RESET->value, $message), $type, $destination, $headers);
  }
}