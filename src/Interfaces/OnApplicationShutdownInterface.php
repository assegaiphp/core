<?php

namespace Assegai\Core\Interfaces;

interface OnApplicationShutdownInterface
{
  public function onApplicationShutdown(): void;
}
