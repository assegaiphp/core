<?php

namespace Assegai\Core\Config;

use Assegai\Core\ApplicationPaths;
use Assegai\Core\Util\Paths;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config\AbstractConfig;
use Assegai\Util\Path;

/**
 * The composer configuration.
 *
 * @package Assegai\Core\Config
 */
#[Injectable]
class ComposerConfig extends AbstractConfig
{
  public function __construct(?ApplicationPaths $paths = null)
  {
    parent::__construct($paths ?? Paths::application());
  }

  /**
   * @inheritDoc
   */
  public function getConfigFilename(): string
  {
    return Path::join($this->applicationPaths?->composerDirectory ?? $this->getWorkingDirectory(), 'composer.json');
  }
}
