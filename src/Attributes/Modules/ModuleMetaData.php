<?php

namespace Assegai\Core\Attributes\Modules;

use Attribute;

#[Attribute]
abstract class ModuleMetaData
{
  /**
   * @param array $providers
   * @param array $controllers
   * @param array $imports
   * @param array $exports
   */
  public function __construct(
    public readonly array $providers = [],
    public readonly array $controllers = [],
    public readonly array $imports = [],
    public readonly array $exports = [],
  )
  {
  }
}