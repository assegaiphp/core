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
}