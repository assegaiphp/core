<?php

namespace Assegai\Core\Http\Cors;

use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Closure;

/**
 * Evaluates a CORS policy and decorates framework responses.
 */
final readonly class CorsProcessor
{
  public function __construct(private CorsOptions $options)
  {
  }

  public function isPreflightRequest(RequestInterface $request): bool
  {
    return $request->getMethod() === RequestMethod::OPTIONS
      && trim($request->header('Origin')) !== ''
      && trim($request->header('Access-Control-Request-Method')) !== '';
  }

  public function shouldShortCircuitPreflight(RequestInterface $request): bool
  {
    return $this->options->origin !== false
      && !$this->options->preflightContinue
      && $this->isPreflightRequest($request);
  }

  public function apply(RequestInterface $request, ResponseInterface $response): void
  {
    if ($this->options->origin === false) {
      return;
    }

    $requestOrigin = $this->normalizeIncomingOrigin($request->header('Origin'));

    if (is_null($requestOrigin)) {
      return;
    }

    if ($this->originPolicyVariesByRequest()) {
      $this->appendVary($response, 'Origin');
    }

    $isPreflight = $this->isPreflightRequest($request);

    if ($isPreflight) {
      $this->appendVary($response, 'Access-Control-Request-Method');

      if (is_null($this->options->allowedHeaders)) {
        $this->appendVary($response, 'Access-Control-Request-Headers');
      }
    }

    $allowedOrigin = $this->resolveAllowedOrigin($requestOrigin, $request);

    if (is_null($allowedOrigin)) {
      return;
    }

    if ($this->options->credentials && $allowedOrigin === '*') {
      return;
    }

    $response->setHeader('Access-Control-Allow-Origin', $allowedOrigin);

    if ($this->options->credentials) {
      $response->setHeader('Access-Control-Allow-Credentials', 'true');
    }

    if ($isPreflight) {
      $this->applyPreflightHeaders($request, $response);
      return;
    }

    if ($this->options->exposedHeaders) {
      $response->setHeader('Access-Control-Expose-Headers', implode(', ', $this->options->exposedHeaders));
    }
  }

  private function applyPreflightHeaders(RequestInterface $request, ResponseInterface $response): void
  {
    $response->setHeader('Access-Control-Allow-Methods', implode(', ', $this->options->methods));
    $allowedHeaders = $this->options->allowedHeaders
      ?? $this->normalizeRequestedHeaders($request->header('Access-Control-Request-Headers'));

    if ($allowedHeaders) {
      $response->setHeader('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
    }

    if (!is_null($this->options->maxAge)) {
      $response->setHeader('Access-Control-Max-Age', (string)$this->options->maxAge);
    }
  }

  private function originPolicyVariesByRequest(): bool
  {
    return $this->options->origin !== '*'
      && !(is_array($this->options->origin) && $this->options->origin === ['*']);
  }

  private function resolveAllowedOrigin(string $requestOrigin, RequestInterface $request): ?string
  {
    return $this->resolveOriginOption($this->options->origin, $requestOrigin, $request);
  }

  private function resolveOriginOption(
    mixed $originOption,
    string $requestOrigin,
    RequestInterface $request,
  ): ?string
  {
    if ($originOption === false || is_null($originOption)) {
      return null;
    }

    if ($originOption === true) {
      return $requestOrigin;
    }

    if ($originOption instanceof Closure) {
      return $this->resolveOriginOption($originOption($requestOrigin, $request), $requestOrigin, $request);
    }

    if (is_array($originOption)) {
      if (in_array('*', $originOption, true)) {
        return '*';
      }

      return in_array($requestOrigin, $originOption, true) ? $requestOrigin : null;
    }

    if (!is_string($originOption)) {
      return null;
    }

    $originOption = trim($originOption);

    if ($originOption === '*') {
      return '*';
    }

    if ($this->normalizeIncomingOrigin($originOption) === null) {
      return null;
    }

    return $originOption === $requestOrigin ? $requestOrigin : null;
  }

  /**
   * @return list<string>
   */
  private function normalizeRequestedHeaders(string $headers): array
  {
    if (trim($headers) === '') {
      return [];
    }

    $normalized = [];

    foreach (explode(',', $headers) as $header) {
      $header = trim($header);

      if ($header === '' || preg_match("/^[!#\$%&'*+\\-.^_`|~0-9A-Za-z]+$/D", $header) !== 1) {
        return [];
      }

      $normalized[strtolower($header)] = $header;
    }

    return array_values($normalized);
  }

  private function normalizeIncomingOrigin(string $origin): ?string
  {
    $origin = trim($origin);

    if ($origin === '' || preg_match('/[\x00-\x1F\x7F]/', $origin) === 1) {
      return null;
    }

    return $origin;
  }

  private function appendVary(ResponseInterface $response, string $value): void
  {
    $values = [];

    foreach ($response->getHeaders() as $header) {
      if (strcasecmp($header['name'], 'Vary') !== 0) {
        continue;
      }

      foreach (explode(',', $header['value']) as $existingValue) {
        $existingValue = trim($existingValue);

        if ($existingValue !== '') {
          $values[strtolower($existingValue)] = $existingValue;
        }
      }
    }

    if (isset($values['*'])) {
      return;
    }

    $values[strtolower($value)] = $value;
    $response->removeHeader('Vary');
    $response->setHeader('Vary', implode(', ', array_values($values)));
  }
}
