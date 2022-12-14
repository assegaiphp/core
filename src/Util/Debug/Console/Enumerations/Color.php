<?php

namespace Assegai\Core\Util\Debug\Console\Enumerations;

enum Color: string
{
  case RESET = "\033[0m";
  case BLACK = "\033[0;30m";
  case RED = "\033[0;31m";
  case GREEN = "\033[0;32m";
  case YELLOW = "\033[0;33m";
  case BLUE = "\033[0;34m";
  case MAGENTA = "\033[0;35m";
  case CYAN = "\033[0;36m";
  case WHITE = "\033[0;37m";
  case BRIGHT_BLACK = "\033[1;30m";
  case BRIGHT_RED = "\033[1;31m";
  case BRIGHT_GREEN = "\033[1;32m";
  case BRIGHT_YELLOW = "\033[1;33m";
  case BRIGHT_BLUE = "\033[1;34m";
  case BRIGHT_MAGENTA = "\033[1;35m";
  case BRIGHT_CYAN = "\033[1;36m";
  case BRIGHT_WHITE = "\033[1;37m";
  case DARK_BLACK = "\033[2;30m";
  case DARK_RED = "\033[2;31m";
  case DARK_GREEN = "\033[2;32m";
  case DARK_YELLOW = "\033[2;33m";
  case DARK_BLUE = "\033[2;34m";
  case DARK_MAGENTA = "\033[2;35m";
  case DARK_CYAN = "\033[2;36m";
  case DARK_WHITE = "\033[2;37m";

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