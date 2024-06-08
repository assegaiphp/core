<?php

namespace Assegai\Core\Attributes\Modules;

use Attribute;

/**
 * The Module attribute is used to define a module in the Assegai framework.
 *
 * @package Assegai\Core\Attributes\Modules
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Module
{
  /**
   * The constructor of the Module attribute.
   *
   * @param string[] $declarations The declarations of the module.
   * @param string[] $providers The providers of the module.
   * @param string[] $controllers The controllers of the module.
   * @param string[] $imports The imports of the module.
   * @param string[] $exports The exports of the module.
   * @param array<string, mixed> $config The configuration of the module.
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