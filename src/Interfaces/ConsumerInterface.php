<?php

namespace Assegai\Core\Interfaces;

/**
 * Interface ConsumerInterface. This interface is for consumers.
 *
 * @package Assegai\Core\Interfaces
 */
interface ConsumerInterface
{
  /**
   * The object or class to be consumed.
   *
   * @param string|object $class The object or class to be consumed.
   * @return $this The consumer instance.
   */
  public function apply(string|object $class): self;

  /**
   * The routes to which the consumer applies.
   *
   * @param array $routes The routes to which the consumer applies.
   * @return $this The consumer instance.
   */
  public function forRoutes(array $routes): self;
}