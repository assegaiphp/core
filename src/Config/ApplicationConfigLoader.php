<?php

namespace Assegai\Core\Config;

use Assegai\Core\Exceptions\ConfigurationException;
use Assegai\Core\Util\Paths;

/**
 * Loads and merges the application's PHP configuration fragments.
 */
final class ApplicationConfigLoader
{
  /**
   * @return array<string, mixed>
   */
  public static function load(string $workingDirectory, bool $production = false): array
  {
    $configDirectory = Paths::join(trim($workingDirectory), 'config');
    $filenames = ['default.php', 'auth.php'];

    if ($production) {
      $filenames[] = 'production.php';
    }

    $filenames[] = 'local.php';
    $filenames[] = 'secure.php';
    $config = [];

    foreach ($filenames as $filename) {
      $path = Paths::join($configDirectory, $filename);

      if (!is_file($path)) {
        continue;
      }

      $fragment = require $path;

      if (!is_array($fragment)) {
        throw new ConfigurationException("Configuration file must return an array: {$path}");
      }

      $config = array_replace_recursive($config, $fragment);
    }

    return $config;
  }
}
