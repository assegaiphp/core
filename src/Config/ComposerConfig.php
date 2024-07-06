<?php

namespace Assegai\Core\Config;

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
  /**
   * @inheritDoc
   */
  public function getConfigFilename(): string
  {
    return Path::join($this->getWorkingDirectory(), 'composer.json');
  }
}