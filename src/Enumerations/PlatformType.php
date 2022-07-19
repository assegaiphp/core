<?php

namespace Assegai\Core;

enum PlatformType
{
  case DEFAULT;
  case LARAVEL;
  case SYMFONY;
  case CODEIGNITER;
  case LAMINAS;
  case CAKE_PHP;
}