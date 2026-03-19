<?php

namespace Assegai\Core\Http\Responses\Interfaces;

use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Http\HttpStatusCode;
use Stringable;
use stdClass;

interface ResponseInterface extends Stringable
{
  public function reset(): void;

  public function toArray(): array;

  public function getStatus(): HttpStatusCode|int;

  public function getStatusCode(): int;

  public function setStatus(HttpStatusCode|int $status): void;

  public function applyStatus(HttpStatusCode|int $status, int $priority = 30): void;

  public function json(array|stdClass|null $body = null): self;

  public function jsonRaw(string|array|object $body): self;

  public function html(string $body): self;

  public function plainText(string $body): self;

  public function redirect(string $url, HttpStatusCode|int $status = 302): self;

  public function applyRedirect(string $url, HttpStatusCode|int $status = 302, int $priority = 30): self;

  public function isRedirect(): bool;

  public function getRedirectUrl(): ?string;

  public function getBody(): string|array|object;

  public function shouldWrapJsonBody(): bool;

  public function setContentType(ContentType $contentType): void;

  public function getContentType(): ContentType;

  public function setBody(string|array|object $body): void;

  public function setHeader(string $name, string $value, bool $replace = true, ?int $statusCode = null): void;

  public function getHeaders(): array;

  public function getHeader(string $name): ?string;

  public function hasHeader(string $name): bool;

  public function removeHeader(string $name): void;

  public function sendHeaders(): void;
}
