<?php

namespace Assegai\Core\Config;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Core\Util\Paths;
use Assegai\Util\Path;
use LogicException;

/**
 * The project configuration.
 *
 * @package Assegai\Core\Config
 */
#[Injectable]
class AppConfig extends AbstractConfig
{
  private readonly string $workingDirectory;

  public function __construct(?string $workingDirectory = null)
  {
    $workingDirectory ??= Paths::getWorkingDirectory();
    $this->workingDirectory = realpath($workingDirectory) ?: $workingDirectory;
    parent::__construct();
  }

  public function getWorkingDirectory(): string
  {
    return $this->workingDirectory;
  }

  /** @return array<string, mixed> */
  public function toArray(): array
  {
    return $this->config;
  }

  /**
   * Returns a detached view of the active application's request configuration.
   * This never loads fragments or falls back to another application's globals.
   */
  public static function current(): self
  {
    $config = RuntimeContext::get(self::class);
    if (!$config instanceof self) {
      throw new LogicException('Application configuration is only available inside an App request scope.');
    }
    return clone $config;
  }

  /**
   * @inheritDoc
   */
  public function load(): void
  {
    $this->config = ApplicationConfigLoader::load(
      $this->getWorkingDirectory(),
      Config::environment() === EnvironmentType::PRODUCTION,
    );
  }

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
