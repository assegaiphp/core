<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Consumers\MiddlewareConsumer;

/**
 * Interface AssegaiModuleInterface. This interface is for Assegai modules.
 *
 * @package Assegai\Core\Interfaces
 */
interface AssegaiModuleInterface
{
  /**
   * Configures the module.
   *
   * @param MiddlewareConsumer $consumer The middleware consumer.
   */
  public function configure(MiddlewareConsumer $consumer): void;
}
