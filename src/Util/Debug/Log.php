<?php

namespace Assegai\Core\Util\Debug;

use Assegai\Core\Util\Debug\Console\Enumerations\Color;

class Log
{
  public static function debug(string $tag, string $message): void
  {
    printf("%sD/%s: %s%s", Color::BLUE->value, $tag, Color::RESET->value, $message);
  }

  public static function info(string $tag, string $message): void
  {
    printf("%sI/%s: %s%s", Color::BLUE->value, $tag, Color::RESET->value, $message);
  }

  public static function warn(string $tag, string $message): void
  {
    printf("%sW/%s: %s%s", Color::BLUE->value, $tag, Color::RESET->value, $message);
  }

  public static function error(string $tag, string $message, int $type = 0, ?string $destination = null, ?string $headers = null): void
  {
    printf("%sE/%s: %s%s", Color::RED->value, $tag, Color::RESET->value, $message);
    error_log($message, $type, $destination, $headers);
  }
}