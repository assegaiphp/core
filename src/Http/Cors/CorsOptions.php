<?php

namespace Assegai\Core\Http\Cors;

use Assegai\Core\Enumerations\Http\RequestMethod;
use Closure;
use InvalidArgumentException;

/**
 * Application-level Cross-Origin Resource Sharing configuration.
 */
final class CorsOptions
{
  /**
   * @var list<string>
   */
  public const array DEFAULT_METHODS = [
    'GET',
    'HEAD',
    'PUT',
    'PATCH',
    'POST',
    'DELETE',
  ];

  /** @var string|list<string>|bool|Closure */
  public readonly string|array|bool|Closure $origin;
  /** @var list<string> */
  public readonly array $methods;
  /** @var list<string>|null */
  public readonly ?array $allowedHeaders;
  /** @var list<string> */
  public readonly array $exposedHeaders;
  public readonly bool $credentials;
  public readonly ?int $maxAge;
  public readonly bool $preflightContinue;
  public readonly int $optionsSuccessStatus;

  /**
   * @param string|list<string>|bool|Closure $origin
   * @param string|list<string|RequestMethod> $methods
   * @param string|list<string>|null $allowedHeaders
   * @param string|list<string> $exposedHeaders
   */
  public function __construct(
    string|array|bool|Closure $origin = '*',
    string|array $methods = self::DEFAULT_METHODS,
    string|array|null $allowedHeaders = null,
    string|array $exposedHeaders = [],
    bool $credentials = false,
    ?int $maxAge = null,
    bool $preflightContinue = false,
    int $optionsSuccessStatus = 204,
  )
  {
    $this->origin = $this->normalizeOrigin($origin);
    $this->methods = $this->normalizeMethods($methods);
    $this->allowedHeaders = is_null($allowedHeaders)
      ? null
      : $this->normalizeHeaderNames($allowedHeaders, 'allowedHeaders');
    $this->exposedHeaders = $this->normalizeHeaderNames($exposedHeaders, 'exposedHeaders');
    $this->credentials = $credentials;
    $this->maxAge = $maxAge;
    $this->preflightContinue = $preflightContinue;
    $this->optionsSuccessStatus = $optionsSuccessStatus;

    $this->assertValid();
  }

  /**
   * @param array<string, mixed>|self|null $options
   */
  public static function from(array|self|null $options = null): self
  {
    if ($options instanceof self) {
      return $options;
    }

    if (is_null($options)) {
      return new self();
    }

    $supportedKeys = [
      'origin',
      'methods',
      'allowedHeaders',
      'exposedHeaders',
      'credentials',
      'maxAge',
      'preflightContinue',
      'optionsSuccessStatus',
    ];
    $unknownKeys = array_values(array_diff(array_keys($options), $supportedKeys));

    if ($unknownKeys) {
      throw new InvalidArgumentException(
        'Unsupported CORS option(s): ' . implode(', ', $unknownKeys) . '.'
      );
    }

    $origin = $options['origin'] ?? '*';

    if (is_callable($origin) && !$origin instanceof Closure) {
      $origin = Closure::fromCallable($origin);
    }

    if (!is_string($origin) && !is_array($origin) && !is_bool($origin) && !$origin instanceof Closure) {
      throw new InvalidArgumentException('CORS origin must be a string, list, boolean, or callable.');
    }

    return new self(
      origin: $origin,
      methods: $options['methods'] ?? self::DEFAULT_METHODS,
      allowedHeaders: $options['allowedHeaders'] ?? null,
      exposedHeaders: $options['exposedHeaders'] ?? [],
      credentials: (bool)($options['credentials'] ?? false),
      maxAge: array_key_exists('maxAge', $options) && !is_null($options['maxAge'])
        ? (int)$options['maxAge']
        : null,
      preflightContinue: (bool)($options['preflightContinue'] ?? false),
      optionsSuccessStatus: (int)($options['optionsSuccessStatus'] ?? 204),
    );
  }

  /**
   * @param string|list<string>|bool|Closure $origin
   * @return string|list<string>|bool|Closure
   */
  private function normalizeOrigin(string|array|bool|Closure $origin): string|array|bool|Closure
  {
    if (!is_array($origin)) {
      if (is_string($origin)) {
        $origin = trim($origin);

        if ($origin === '') {
          throw new InvalidArgumentException('CORS origin cannot be empty.');
        }

        $this->assertSafeHeaderValue($origin, 'origin');
      }

      return $origin;
    }

    $origins = [];

    foreach ($origin as $value) {
      if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException('Every CORS origin must be a non-empty string.');
      }

      $value = trim($value);
      $this->assertSafeHeaderValue($value, 'origin');
      $origins[$value] = $value;
    }

    if (isset($origins['*'])) {
      return ['*'];
    }

    return array_values($origins);
  }

  /**
   * @param string|list<string|RequestMethod> $methods
   * @return list<string>
   */
  private function normalizeMethods(string|array $methods): array
  {
    $values = is_string($methods) ? explode(',', $methods) : $methods;
    $normalized = [];

    foreach ($values as $method) {
      if ($method instanceof RequestMethod) {
        $method = $method->value;
      }

      if (!is_string($method) || trim($method) === '') {
        throw new InvalidArgumentException('Every CORS method must be a non-empty string or RequestMethod.');
      }

      $method = strtoupper(trim($method));
      $this->assertHttpToken($method, 'method');
      $normalized[$method] = $method;
    }

    if (!$normalized) {
      throw new InvalidArgumentException('At least one CORS method must be configured.');
    }

    return array_values($normalized);
  }

  /**
   * @param string|list<string> $headers
   * @return list<string>
   */
  private function normalizeHeaderNames(string|array $headers, string $optionName): array
  {
    $values = is_string($headers) ? explode(',', $headers) : $headers;
    $normalized = [];

    foreach ($values as $header) {
      if (!is_string($header) || trim($header) === '') {
        throw new InvalidArgumentException("Every $optionName entry must be a non-empty header name.");
      }

      $header = trim($header);
      $this->assertHttpToken($header, $optionName);
      $normalized[strtolower($header)] = $header;
    }

    return array_values($normalized);
  }

  private function assertValid(): void
  {
    $allowsAnyOrigin = $this->origin === true
      || $this->origin === '*'
      || (is_array($this->origin) && in_array('*', $this->origin, true));

    if ($this->credentials && $allowsAnyOrigin) {
      throw new InvalidArgumentException(
        'Credentialed CORS requires an explicit origin allowlist or origin callback; wildcard origins are not permitted.'
      );
    }

    if (!is_null($this->maxAge) && $this->maxAge < 0) {
      throw new InvalidArgumentException('CORS maxAge must be zero or greater.');
    }

    if ($this->optionsSuccessStatus < 200 || $this->optionsSuccessStatus > 299) {
      throw new InvalidArgumentException('CORS optionsSuccessStatus must be a successful HTTP status code.');
    }
  }

  private function assertHttpToken(string $value, string $field): void
  {
    if (preg_match("/^[!#\$%&'*+\\-.^_`|~0-9A-Za-z]+$/D", $value) !== 1) {
      throw new InvalidArgumentException("Invalid CORS $field value [$value].");
    }
  }

  private function assertSafeHeaderValue(string $value, string $field): void
  {
    if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
      throw new InvalidArgumentException("Invalid control character in CORS $field value.");
    }
  }
}
