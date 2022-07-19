<?php

namespace Assegai\Core\Util\Debug\Console\Enumerations;

enum BackgroundColor: string
{
  case RESET = "\033[0m";
  case BLACK = "\033[0;40m";
  case RED = "\033[0;41m";
  case GREEN = "\033[0;42m";
  case YELLOW = "\033[0;43m";
  case BLUE = "\033[0;44m";
  case MAGENTA = "\033[0;45m";
  case CYAN = "\033[0;46m";
  case WHITE = "\033[0;47m";
  case BRIGHT_BLACK = "\033[1;40m";
  case BRIGHT_RED = "\033[1;41m";
  case BRIGHT_GREEN = "\033[1;42m";
  case BRIGHT_YELLOW = "\033[1;43m";
  case BRIGHT_BLUE = "\033[1;44m";
  case BRIGHT_MAGENTA = "\033[1;45m";
  case BRIGHT_CYAN = "\033[1;46m";
  case BRIGHT_WHITE = "\033[1;47m";
  case DARK_BLACK = "\033[2;40m";
  case DARK_RED = "\033[2;41m";
  case DARK_GREEN = "\033[2;42m";
  case DARK_YELLOW = "\033[2;43m";
  case DARK_BLUE = "\033[2;44m";
  case DARK_MAGENTA = "\033[2;45m";
  case DARK_CYAN = "\033[2;46m";
  case DARK_WHITE = "\033[2;47m";

  public function name(): string
  {
    return match($this) {
      self::RESET => 'none',
      self::BLACK => 'black',
      self::RED => 'red',
      self::GREEN => 'green',
      self::YELLOW => 'yellow',
      self::BLUE => 'blue',
      self::MAGENTA => 'magenta',
      self::CYAN => 'cyan',
      self::WHITE => 'white',
      self::BRIGHT_BLACK => 'bright black',
      self::BRIGHT_RED => 'bright red',
      self::BRIGHT_GREEN => 'bright green',
      self::BRIGHT_YELLOW => 'bright yellow',
      self::BRIGHT_BLUE => 'bright blue',
      self::BRIGHT_MAGENTA => 'bright magenta',
      self::BRIGHT_CYAN => 'bright cyan',
      self::BRIGHT_WHITE => 'bright white',
      self::DARK_BLACK => 'dark black',
      self::DARK_RED => 'dark red',
      self::DARK_GREEN => 'dark green',
      self::DARK_YELLOW => 'dark yellow',
      self::DARK_BLUE => 'dark blue',
      self::DARK_MAGENTA => 'dark magenta',
      self::DARK_CYAN => 'dark cyan',
      self::DARK_WHITE => 'dark white'
    };
  }
}