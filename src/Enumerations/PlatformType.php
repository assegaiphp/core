<?php

namespace Assegai\Core\Enumerations;

/**
 * Enumerates the different types of platforms that Assegai supports.
 */
enum PlatformType
{
  case DEFAULT;
  case LARAVEL;
  case SYMFONY;
  case CODEIGNITER;
  case LAMINAS;
  case CAKE_PHP;
}