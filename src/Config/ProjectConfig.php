<?php

namespace Assegai\Core\Config;

use Assegai\Core\Attributes\Injectable;
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

    if (!isset($_ENV['ENV'])) {
      $_ENV['ENV'] = 'prod';
    }

    if (!isset($_ENV['DEBUG_MODE'])) {
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