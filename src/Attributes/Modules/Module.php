<?php

namespace Assegai\Core\Attributes\Modules;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Module
{
  /**
   * @param array $declarations
   * @param array $providers
   * @param array $controllers
   * @param array $imports
   * @param array $exports
   * @param array $config
   */
  public function __construct(
    public array $declarations = [],
    public array $providers = [],
    public array $controllers = [],
    public array $imports = [],
    public array $exports = [],
    public array $config = [],
  )
  {
  }
}