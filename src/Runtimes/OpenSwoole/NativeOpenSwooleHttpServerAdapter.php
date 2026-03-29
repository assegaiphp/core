<?php

namespace Assegai\Core\Runtimes\OpenSwoole;

use Assegai\Core\Runtimes\OpenSwoole\Interfaces\OpenSwooleHttpServerInterface;

class NativeOpenSwooleHttpServerAdapter implements OpenSwooleHttpServerInterface
{
  public function __construct(
    private readonly object $server,
  )
  {
  }

  public function set(array $settings): void
  {
    if (method_exists($this->server, 'set')) {
      $this->server->set($settings);
    }
  }

  public function on(string $event, callable $handler): void
  {
    $this->server->on($event, $handler);
  }

  public function start(): void
  {
    $this->server->start();
  }
}
