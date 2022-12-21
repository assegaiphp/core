<?php

namespace Assegai\Core\Common;

use Assegai\Core\Interfaces\IDynamicModule;
use ReflectionClass;

/**
 *
 */
class DynamicModule implements IDynamicModule
{
  /**
   * @param string|ReflectionClass $module
   * @param array $providers
   * @param array $controllers
   * @param array $imports
   * @param array $exports
   * @param array $config
   */
  public function __construct(
    public readonly string|ReflectionClass $module,
    public readonly array $providers = [],
    public readonly array $controllers = [],
    public readonly array $imports = [],
    public readonly array $exports = [],
    public readonly array $config = [],
  )
  {
  }

  /**
   * @param DynamicModuleOptions|null $options
   * @return IDynamicModule
   */
  public static function forRoot(?DynamicModuleOptions $options = null): IDynamicModule
  {
    // TODO: Implement forRoot() method.
    return new DynamicModule(self::class);
  }

  /**
   * @param DynamicModuleOptions|null $options
   * @return IDynamicModule
   */
  public static function forFeature(?DynamicModuleOptions $options = null): IDynamicModule
  {
    // TODO: Implement forFeature() method.
    return new DynamicModule(self::class);
  }
}