<?php

namespace Assegai\Core\Attributes\Modules;

readonly class ModuleMetaData
{
  /**
   * @param string[] $declarations
   * @param string[] $providers
   * @param string[] $controllers
   * @param string[] $imports
   * @param string[] $exports
   * @param array<string, mixed> $config
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