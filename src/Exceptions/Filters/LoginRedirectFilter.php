<?php

namespace Assegai\Core\Exceptions\Filters;

use Assegai\Core\ArgumentsHost;
use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\OnException;
use Assegai\Core\Config\AppConfig;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Exceptions\Http\UnauthorizedException;
use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Session;
use Throwable;

/**
 * Redirects unauthenticated browser flows to an application-configured login URL.
 */
#[OnException(UnauthorizedException::class)]
#[Injectable]
final readonly class LoginRedirectFilter implements ExceptionFilterInterface
{
  public LoginRedirectFilterOptions $options;

  public function __construct(
    ?LoginRedirectFilterOptions $options = null,
    ?AppConfig $appConfig = null,
  )
  {
    $authenticationConfig = $appConfig?->get('authentication');
    $this->options = $options ?? LoginRedirectFilterOptions::fromConfig(
      is_array($authenticationConfig) ? $authenticationConfig : null,
    );
  }

  /**
   * @inheritDoc
   */
  public function catch(Throwable $throwable, ArgumentsHost $host): void
  {
    $http = $host->switchToHttp();
    $request = $http->getRequest();
    $response = $http->getResponse();

    if ($this->isExcludedPath($request->getPath())) {
      $this->prepareUnauthorizedResponse($response);
      return;
    }

    $this->rememberIntendedTarget($request, $http->getSession());

    $response->reset();
    $response->redirect($this->options->loginUrl, $this->options->statusCode);
    $response->setHeader('Cache-Control', 'no-store');
  }

  private function prepareUnauthorizedResponse(ResponseInterface $response): void
  {
    $response->reset();
    $response->setStatus(401);
    $response->jsonRaw([
      'statusCode' => 401,
      'message' => 'Unauthorized',
      'error' => 'Unauthorized',
    ]);
    $response->setHeader('Cache-Control', 'no-store');
  }

  private function rememberIntendedTarget(RequestInterface $request, Session $session): void
  {
    if (!$this->options->preserveTarget) {
      return;
    }

    if (!in_array($request->getMethod(), [RequestMethod::GET, RequestMethod::HEAD], true)) {
      return;
    }

    $target = $this->safeLocalTarget($request);

    if ($target !== null) {
      $session->set($this->options->targetSessionKey, $target);
    }
  }

  private function safeLocalTarget(RequestInterface $request): ?string
  {
    $uri = trim($request->getUri());

    if ($uri === '' || str_contains($uri, '\\') || preg_match('/[\r\n]/', $uri)) {
      return null;
    }

    $parts = parse_url($uri);

    if ($parts === false) {
      return null;
    }

    $host = $parts['host'] ?? null;

    if (is_string($host) && strcasecmp($host, $request->getHostName()) !== 0) {
      return null;
    }

    $path = $parts['path'] ?? '/';

    if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return null;
    }

    $query = $parts['query'] ?? null;

    return is_string($query) && $query !== '' ? $path . '?' . $query : $path;
  }

  private function isExcludedPath(string $requestPath): bool
  {
    $requestPath = $this->normalizePath($requestPath);

    foreach ($this->options->effectiveExcludedPaths() as $excludedPath) {
      if ($requestPath === $this->normalizePath($excludedPath)) {
        return true;
      }
    }

    return false;
  }

  private function normalizePath(string $path): string
  {
    $parsedPath = parse_url($path, PHP_URL_PATH);
    $path = is_string($parsedPath) ? $parsedPath : $path;

    return '/' . trim($path, '/');
  }
}
