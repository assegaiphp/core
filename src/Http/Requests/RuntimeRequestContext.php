<?php

namespace Assegai\Core\Http\Requests;

final class RuntimeRequestContext
{
  /**
   * @param array<string, mixed> $server
   * @param array<string, mixed> $query
   * @param array<string, mixed> $post
   * @param array<string, mixed> $cookies
   * @param array<string, mixed> $files
   * @param string|null $rawBody
   */
  public function __construct(
    public readonly array $server = [],
    public readonly array $query = [],
    public readonly array $post = [],
    public readonly array $cookies = [],
    public readonly array $files = [],
    public readonly ?string $rawBody = null,
  )
  {
  }

  /**
   * Captures the current PHP superglobals into a runtime-neutral request snapshot.
   *
   * @return self
   */
  public static function fromGlobals(): self
  {
    return new self(
      server: $_SERVER,
      query: $_GET,
      post: $_POST,
      cookies: $_COOKIE,
      files: $_FILES,
      rawBody: file_get_contents('php://input') ?: null,
    );
  }
}
