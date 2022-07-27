<?php

namespace Assegai\Core\Interfaces;

interface IConsumer
{
  public function apply(string|object $class): self;

  public function forRoutes(array $routes): self;
}