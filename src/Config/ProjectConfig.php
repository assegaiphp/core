<?php

namespace Assegai\Core\Config;

use Assegai\Core\ApplicationPaths;
use Assegai\Core\Util\Paths;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Config as RootConfig;
use Assegai\Core\Enumerations\Http\ContextType;
use Assegai\Core\Interfaces\ConfigInterface;
use Assegai\Util\Path;
use Dotenv\Dotenv;
use InvalidArgumentException;

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
    public ContextType $type = ContextType::HTTP,
    ?ApplicationPaths $paths = null,
  )
  {
    $this->applicationPaths = $paths ?? Paths::application();
    if ($this->applicationPaths !== null) {
      $this->applicationPaths->loadEnvironment(required: false);
    } elseif (file_exists(Path::join($this->getWorkingDirectory(), '.env'))) {
      $dotenv = Dotenv::createImmutable($this->getWorkingDirectory());
      $dotenv->safeLoad();
    }

    if (RootConfig::environmentValue('ENV') === null && RootConfig::environmentValue('APP_ENV') === null) {
      $_ENV['ENV'] = 'prod';
    }

    if (RootConfig::environmentValue('DEBUG_MODE') === null && RootConfig::environmentValue('APP_DEBUG') === null) {
      $_ENV['DEBUG_MODE'] = false;
    }

    parent::__construct($this->applicationPaths);
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

  /**
   * Standalone rendering can resolve project metadata without loading dotenv or constructing an App.
   */
  public static function resolveSourceDirectory(string $workingDirectory): string
  {
    $filename = Path::join($workingDirectory, 'assegai.json');
    $configuration = is_file($filename)
      ? json_decode(file_get_contents($filename) ?: '', true, flags: JSON_THROW_ON_ERROR)
      : [];
    if (!is_array($configuration)) {
      throw new InvalidArgumentException('Project configuration must contain an object.');
    }

    $sourceRoot = array_key_exists('sourceRoot', $configuration) ? $configuration['sourceRoot'] : 'src';
    if (!is_string($sourceRoot) || $sourceRoot === '' || str_starts_with($sourceRoot, '/')
      || preg_match('~[\\\\:\x00-\x1f\x7f]~', $sourceRoot)) {
      throw new InvalidArgumentException('sourceRoot must be a contained relative directory.');
    }
    foreach (explode('/', $sourceRoot) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        throw new InvalidArgumentException('sourceRoot must not contain empty or dot path segments.');
      }
    }

    $root = realpath($workingDirectory);
    if ($root === false || !is_dir($root)) {
      throw new InvalidArgumentException('The application root must be an existing directory.');
    }
    $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourceRoot);
    $directory = realpath($candidate);
    // Keep the legacy default usable for callers preparing a source tree before rendering.
    if ($directory === false && $sourceRoot === 'src' && !is_link($candidate)) {
      return $candidate;
    }
    if ($directory === false || !is_dir($directory)
      || !str_starts_with($directory, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
      throw new InvalidArgumentException('sourceRoot must resolve to an existing directory inside the application.');
    }
    return $directory;
  }
}
