<?php

namespace Assegai\Core\Interfaces;

interface HttpRuntimeInterface
{
  /**
   * Returns the runtime name.
   */
  public function getName(): string;

  /**
   * Runs the application through the current HTTP runtime.
   *
   * @param AppInterface $app
   * @param callable $handler The default application lifecycle callback.
   * @return void
   */
  public function run(AppInterface $app, callable $handler): void;
}
