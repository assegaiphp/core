<?php

namespace Assegai\Core\Config;

use Assegai\Core\Config\AbstractConfig;
use Assegai\Util\Path;

/**
 * The composer configuration.
 *
 * @package Assegai\Core\Config
 */
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