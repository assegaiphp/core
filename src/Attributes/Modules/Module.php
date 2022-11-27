<?php

namespace Assegai\Core\Attributes\Modules;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Module extends ModuleMetaData
{
  /**
   * @param array $providers
   * @param array $controllers
   * @param array $imports
   * @param array $exports
   * @param array $config
   */
  public function __construct(
    public readonly array $providers = [],
    public readonly array $controllers = [],
    public readonly array $imports = [],
    public readonly array $exports = [],
    public readonly array $config = [],
  )
  {
    parent::__construct($this->providers,$this->controllers,$this->imports,$this->exports);
  }
}