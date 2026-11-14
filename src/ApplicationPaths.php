<?php

declare(strict_types=1);

namespace Assegai\Core;

use Dotenv\Dotenv;
use InvalidArgumentException;

/**
 * Bootstrap-time filesystem locations for one application.
 *
 * Configuration and resources are read-only inputs. State is owned by the
 * deployment and must survive replacement of the generated application.
 */
final readonly class ApplicationPaths
{
  public string $applicationRoot;
  public string $configurationDirectory;
  public string $secretsDirectory;
  public string $stateDirectory;
  public string $composerDirectory;
  public ?string $environmentFile;
  public bool $hasExplicitStateDirectory;

  public function __construct(
    string $applicationRoot,
    ?string $configurationDirectory = null,
    ?string $secretsDirectory = null,
    ?string $stateDirectory = null,
    ?string $composerDirectory = null,
    ?string $environmentFile = '.env',
    public bool $readOnly = true,
  ) {
    $root = realpath(self::absolute($applicationRoot));
    if ($root === false || !is_dir($root)) {
      throw new InvalidArgumentException('The application root must be an existing directory.');
    }
    $this->applicationRoot = $root;
    $this->configurationDirectory = self::directory($configurationDirectory, "$root/config");
    $this->secretsDirectory = self::directory($secretsDirectory, $this->configurationDirectory);
    $this->stateDirectory = self::directory($stateDirectory, $root);
    $this->composerDirectory = self::directory($composerDirectory, $root);
    $this->hasExplicitStateDirectory = $stateDirectory !== null;
    $this->environmentFile = $environmentFile === null ? null : self::absolute($environmentFile, $root);
  }

  /** Load only the selected file. Null explicitly selects a process-environment-only application. */
  public function loadEnvironment(bool $required = true): void
  {
    if ($this->environmentFile === null) {
      return;
    }
    $dotenv = Dotenv::createImmutable(dirname($this->environmentFile), basename($this->environmentFile));
    $required ? $dotenv->load() : $dotenv->safeLoad();
  }

  public function configurationFile(string $name): string
  {
    $directory = in_array($name, ['local.php', 'secure.php'], true)
      ? $this->secretsDirectory
      : $this->configurationDirectory;
    return $directory . DIRECTORY_SEPARATOR . $name;
  }

  private static function directory(?string $selected, string $default): string
  {
    if ($selected === null) {
      return $default;
    }
    $path = self::absolute($selected);
    $resolved = realpath($path);
    if ($resolved === false || !is_dir($resolved)) {
      throw new InvalidArgumentException('Explicit application directories must already exist.');
    }
    return $resolved;
  }

  private static function absolute(string $path, ?string $base = null): string
  {
    if ($path === '' || preg_match('/[\x00-\x1f\x7f]/', $path) === 1 || str_contains($path, '://')) {
      throw new InvalidArgumentException('Application paths must be local filesystem paths.');
    }
    $absolute = str_starts_with($path, DIRECTORY_SEPARATOR)
      || str_starts_with($path, '//')
      || preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) === 1;
    if (!$absolute && $base !== null) {
      $path = $base . DIRECTORY_SEPARATOR . $path;
    } elseif (!$absolute) {
      throw new InvalidArgumentException('Explicit application directories must use absolute paths.');
    }
    return $path;
  }
}
