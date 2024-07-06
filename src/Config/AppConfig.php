<?php

namespace Assegai\Core\Config;

use Assegai\Core\Attributes\Injectable;
use Assegai\Util\Path;

/**
 * The project configuration.
 *
 * @package Assegai\Core\Config
 */
#[Injectable]
class AppConfig extends AbstractConfig
{
  /**
   * @inheritDoc
   */
  public function getConfigFilename(): string
  {
    $configDirectory = Path::join($this->getWorkingDirectory(), 'config');
    $possibleConfigFilenames = ['default.php', 'local.php', 'dev.php', 'secure.php'];
    $configFilename = Path::join($configDirectory, 'default.php');

    foreach ($possibleConfigFilenames as $filename) {
      $configPath = Path::join($configDirectory, $filename);

      if (file_exists($configPath)) {
        $configFilename = $configPath;
        break;
      }
    }

    return $configFilename;
  }
}