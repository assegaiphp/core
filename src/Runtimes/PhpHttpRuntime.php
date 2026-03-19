<?php

namespace Assegai\Core\Runtimes;

use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\HttpRuntimeInterface;

class PhpHttpRuntime implements HttpRuntimeInterface
{
  public function getName(): string
  {
    return 'php';
  }

  public function run(AppInterface $app, callable $handler): void
  {
    $handler();
  }
}
