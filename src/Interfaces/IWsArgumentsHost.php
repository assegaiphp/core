<?php

namespace Assegai\Core\Interfaces;

/**
 * Specifies methods to obtain WebSocket data and client objects.
 */
interface IWsArgumentsHost
{
  /**
   * @return mixed Returns the data object.
   */
  public function getData(): mixed;

  /**
   * @return mixed Returns the client object.
   */
  public function getClient(): mixed;

  /**
   * @return array
   */
  public function getArgs(): array;
}