<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Enumerations\Scope;
use Assegai\Core\ScopeOptions as InjectableOptions;
use Attribute;

/**
 * An attribute that marks a class as available to be provided and injected as a dependency.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Injectable
{
  /**
   * @param InjectableOptions|array{scope: Scope, durable: bool}|null $options
   */
  public function __construct(
    public InjectableOptions|array|null $options = null,
  )
  {
  }
}