<?php

namespace Assegai\Core\Interfaces;

use ReflectionMethod;

interface IExecutionContext extends IArgumentHost
{
  /**
   * Returns the type of the controller class which the current handler belongs to.
   * @return string
   */
  public function getClass(): string;

  /**
   * Returns a reference to the handler (method) that will be invoked next in the
   * request pipeline.
   * @return callable|ReflectionMethod
   */
  public function getHandler(): callable|ReflectionMethod;
}