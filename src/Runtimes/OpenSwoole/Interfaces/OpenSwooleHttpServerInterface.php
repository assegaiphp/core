<?php

namespace Assegai\Core\Runtimes\OpenSwoole\Interfaces;

interface OpenSwooleHttpServerInterface
{
  /**
   * @param array<string, mixed> $settings
   */
  public function set(array $settings): void;

  public function on(string $event, callable $handler): void;

  public function start(): void;
}
