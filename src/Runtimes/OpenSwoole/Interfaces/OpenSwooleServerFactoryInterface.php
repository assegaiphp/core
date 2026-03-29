<?php

namespace Assegai\Core\Runtimes\OpenSwoole\Interfaces;

interface OpenSwooleServerFactoryInterface
{
  public function create(string $host, int $port): OpenSwooleHttpServerInterface;
}
