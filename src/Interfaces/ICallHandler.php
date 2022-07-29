<?php

namespace Assegai\Core\Interfaces;

/**
 * Interface providing access to the response stream.
 *
 * @see [Interceptors](https://docs.assegaiphp.com/interceptors)
 *
 */
interface ICallHandler
{
  /**
   * Returns an `Observable` representing the response stream from the route
   * handler.
   * @return mixed
   */
  public function handle(): mixed;
}