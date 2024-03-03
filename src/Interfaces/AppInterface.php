<?php

namespace Assegai\Core\Interfaces;

use Psr\Log\LoggerInterface;

interface AppInterface
{
  /**
   * Sets the app configuration to the given config properties.
   *
   * @param mixed|null $config The configuration properties.
   * @return self
   */
  public function configure(mixed $config = null): self;

  /**
   * Specifies a list of pipes that should be used by the `App` instance.
   *
   * @param IPipeTransform|array $pipes A list of pipes to be used by the `App` instance.
   * @return static The current `App` instance.
   */
  public function useGlobalPipes(IPipeTransform|array $pipes): self;

  /**
   * Sets a logger instance that should be user by the `App` instance.
   *
   * @param LoggerInterface $logger The logger instance to be used by the `App` instance.
   * @return self
   */
  public function setLogger(LoggerInterface $logger): self;

  /**
   * Runs the current application.
   *
   * @return void
   */
  public function run(): void;
}