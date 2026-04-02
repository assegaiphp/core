<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Injector;
use ReflectionParameter;

interface ParameterResolverInterface
{
  public function supports(ReflectionParameter $parameter, Injector $injector): bool;

  public function resolve(ReflectionParameter $parameter, Injector $injector): mixed;
}
