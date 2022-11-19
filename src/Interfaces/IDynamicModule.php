<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Common\DynamicModuleOptions;

interface IDynamicModule
{
  public static function forRoot(?DynamicModuleOptions $options = null): IDynamicModule;
  public static function forFeature(?DynamicModuleOptions $options = null): IDynamicModule;
}