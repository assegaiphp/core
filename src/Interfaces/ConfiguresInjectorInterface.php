<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Injector;

interface ConfiguresInjectorInterface
{
  public function configureInjector(Injector $injector): void;
}
