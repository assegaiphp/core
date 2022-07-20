<?php

namespace Assegai\Core\Interfaces;

/**
 * Specifies methods to obtain RPC data objects.
 */
interface IRpcArgumentsHost
{
  /**
   * @return mixed Returns the data object.
   */
  public function getData(): mixed;

  /**
   * @return mixed Returns the context object.
   */
  public function getContext(): mixed;
}