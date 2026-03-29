<?php

namespace Assegai\Core\Runtimes\OpenSwoole;

use Assegai\Core\Runtimes\OpenSwoole\Interfaces\OpenSwooleHttpServerInterface;
use Assegai\Core\Runtimes\OpenSwoole\Interfaces\OpenSwooleServerFactoryInterface;

class NativeOpenSwooleServerFactory implements OpenSwooleServerFactoryInterface
{
  public function create(string $host, int $port): OpenSwooleHttpServerInterface
  {
    (new OpenSwooleRuntimeInspector())->assertAvailable();

    $serverClass = '\\OpenSwoole\\HTTP\\Server';

    return new NativeOpenSwooleHttpServerAdapter(new $serverClass($host, $port));
  }
}
