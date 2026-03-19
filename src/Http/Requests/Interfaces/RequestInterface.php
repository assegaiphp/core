<?php

namespace Assegai\Core\Http\Requests\Interfaces;

use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Http\Requests\RequestQuery;
use Assegai\Core\Interfaces\AppInterface;
use stdClass;

interface RequestInterface
{
  public function getApp(): ?AppInterface;

  public function setApp(?AppInterface $app): void;

  public function toArray(): array;

  public function toJSON(): string;

  public function header(string $name): string;

  public function allHeaders(): array;

  public function getUri(): string;

  public function getPath(): string;

  public function path(): string;

  public function getLimit(): int;

  public function getSkip(): int;

  public function getBody(): ?stdClass;

  public function setBody(?stdClass $body): void;

  public function getCookies(?string $name = null): array|string;

  public function getLang(): string;

  public function fresh(): bool;

  public function getHostName(): string;

  public function getMethod(): RequestMethod;

  public function getRemoteIp(): string;

  public function getProtocol(): string;

  public function getParams(): array;

  public function getHostParams(): array;

  public function setParams(array $params): void;

  public function setHostParams(array $params): void;

  public function clearParams(): void;

  public function clearHostParams(): void;

  public function getQuery(): RequestQuery;

  public function accessToken(bool $deconstruct = false): null|string|stdClass|false;

  public function extractParams(string $path, string $pattern): void;

  public function getFile(): object|array;

  public function setFile(array|object $file): void;
}
