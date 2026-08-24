<?php

namespace Assegai\Core\Exceptions\Filters;

use Assegai\Core\Config;
use InvalidArgumentException;

/**
 * Configures the built-in unauthenticated browser redirect policy.
 */
final readonly class LoginRedirectFilterOptions
{
  private const array REDIRECT_STATUS_CODES = [301, 302, 303, 307, 308];

  /**
   * @param string $loginUrl Application-owned login URL.
   * @param int $statusCode HTTP redirect status.
   * @param bool $preserveTarget Whether safe requested URLs should be retained in the session.
   * @param string $targetSessionKey Dot-delimited session key for the retained URL.
   * @param array<int, string> $excludedPaths Paths that must return 401 instead of redirecting.
   */
  public function __construct(
    public string $loginUrl,
    public int $statusCode = 302,
    public bool $preserveTarget = true,
    public string $targetSessionKey = 'auth.intended_url',
    public array $excludedPaths = [],
  )
  {
    if (trim($this->loginUrl) === '' || preg_match('/[\r\n]/', $this->loginUrl)) {
      throw new InvalidArgumentException('The login redirect URL must be a non-empty, single-line URL.');
    }

    $loginUrlParts = parse_url($this->loginUrl);
    $scheme = is_array($loginUrlParts) ? ($loginUrlParts['scheme'] ?? null) : null;

    if (
      $loginUrlParts === false ||
      str_starts_with($this->loginUrl, '//') ||
      (is_string($scheme) && !in_array(strtolower($scheme), ['http', 'https'], true))
    ) {
      throw new InvalidArgumentException('The login redirect URL must be a relative or HTTP(S) URL.');
    }

    if (!in_array($this->statusCode, self::REDIRECT_STATUS_CODES, true)) {
      throw new InvalidArgumentException('The login redirect status must be 301, 302, 303, 307, or 308.');
    }

    if ($this->preserveTarget && trim($this->targetSessionKey) === '') {
      throw new InvalidArgumentException('The intended-target session key cannot be empty.');
    }

    foreach ($this->excludedPaths as $path) {
      if (!is_string($path) || trim($path) === '' || preg_match('/[\r\n]/', $path)) {
        throw new InvalidArgumentException('Excluded login redirect paths must be non-empty, single-line strings.');
      }
    }
  }

  /**
   * Builds the redirect policy from the application's `config/auth.php` fragment.
   *
   * @param array<string, mixed>|null $authenticationConfig
   */
  public static function fromConfig(?array $authenticationConfig = null): self
  {
    $authenticationConfig ??= Config::get('authentication');
    $config = is_array($authenticationConfig['loginRedirect'] ?? null)
      ? $authenticationConfig['loginRedirect']
      : null;

    if ($config === null || !is_string($config['url'] ?? null)) {
      throw new InvalidArgumentException(
        'Configure authentication.loginRedirect.url in config/auth.php before using LoginRedirectFilter by class name.'
      );
    }

    $statusCode = $config['statusCode'] ?? 302;
    $preserveTarget = $config['preserveTarget'] ?? true;
    $targetSessionKey = $config['targetSessionKey'] ?? 'auth.intended_url';
    $excludedPaths = $config['excludedPaths'] ?? [];

    if (!is_int($statusCode)) {
      throw new InvalidArgumentException('authentication.loginRedirect.statusCode must be an integer.');
    }

    if (!is_bool($preserveTarget)) {
      throw new InvalidArgumentException('authentication.loginRedirect.preserveTarget must be a boolean.');
    }

    if (!is_string($targetSessionKey)) {
      throw new InvalidArgumentException('authentication.loginRedirect.targetSessionKey must be a string.');
    }

    if (!is_array($excludedPaths)) {
      throw new InvalidArgumentException('authentication.loginRedirect.excludedPaths must be an array.');
    }

    return new self(
      loginUrl: $config['url'],
      statusCode: $statusCode,
      preserveTarget: $preserveTarget,
      targetSessionKey: $targetSessionKey,
      excludedPaths: $excludedPaths,
    );
  }

  /**
   * @return array<int, string>
   */
  public function effectiveExcludedPaths(): array
  {
    $loginPath = parse_url($this->loginUrl, PHP_URL_PATH);
    $paths = $this->excludedPaths;

    if (is_string($loginPath) && $loginPath !== '') {
      $paths[] = $loginPath;
    }

    return array_values(array_unique($paths));
  }
}
