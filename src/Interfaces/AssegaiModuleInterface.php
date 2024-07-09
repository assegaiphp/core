<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Consumers\MiddlewareConsumerInterface;

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
   * @param MiddlewareConsumerInterface $consumer The middleware consumer.
   */
  public function configure(MiddlewareConsumerInterface $consumer): void;
}