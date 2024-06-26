<?php

namespace Assegai\Core\Config;

use Assegai\Core\Enumerations\Http\ContextType;
use Assegai\Core\Interfaces\ConfigInterface;
use Assegai\Util\Path;
use Dotenv\Dotenv;
use RuntimeException;

/**
 * The app configuration.
 *
 * @package Assegai\Core
 */
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
    $dotenv = Dotenv::createImmutable($this->getWorkingDirectory());
    $dotenv->safeLoad();
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