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
   * @param array|string|object ...$class The object or class to be consumed.
   * @return $this The consumer instance.
   */
  public function apply(array|string|object ...$class): self;

  /**
   * Excludes routes from the current consumer registration.
   *
   * @param array|string|object ...$routes The routes to exclude.
   * @return $this The consumer instance.
   */
  public function exclude(array|string|object ...$routes): self;

  /**
   * The routes to which the consumer applies.
   *
   * @param array|string|object ...$routes The routes to which the consumer applies.
   * @return $this The consumer instance.
   */
  public function forRoutes(array|string|object ...$routes): self;
}
