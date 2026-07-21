<?php

namespace Assegai\Core\Config;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config as RootConfig;
use Assegai\Core\Enumerations\Http\ContextType;
use Assegai\Core\Interfaces\ConfigInterface;
use Assegai\Util\Path;
use Dotenv\Dotenv;

/**
 * The app configuration.
 *
 * @package Assegai\Core
 */
#[Injectable]
class ProjectConfig extends AbstractConfig
{
  /**
   * AppConfig constructor.
   *
   * @param ContextType $type The context type.
   */
  public function __construct(
    public ContextType $type = ContextType::HTTP
  )
  {
    if (file_exists(Path::join($this->getWorkingDirectory(), '.env'))) {
      $dotenv = Dotenv::createImmutable($this->getWorkingDirectory());
      $dotenv->safeLoad();
    }

    if (RootConfig::environmentValue('ENV') === null && RootConfig::environmentValue('APP_ENV') === null) {
      $_ENV['ENV'] = 'prod';
    }

    if (RootConfig::environmentValue('DEBUG_MODE') === null && RootConfig::environmentValue('APP_DEBUG') === null) {
      $_ENV['DEBUG_MODE'] = false;
    }

    parent::__construct();
  }

  /**
   * Returns the context type.
   *
   * @return ContextType The context type.
   */
  public function getType(): ContextType
  {
    return $this->type;
  }

  /**
   * @inheritDoc
   */
  public function getConfigFilename(): string
  {
    return Path::join($this->getWorkingDirectory(), 'assegai.json');
  }
}
