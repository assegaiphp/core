<?php

namespace Assegai\Core\WebSockets;

use Assegai\Core\Interfaces\IWsArgumentsHost;

class WsArgumentsHost implements IWsArgumentsHost
{
  protected static ?WsArgumentsHost $instance = null;

  private final function __construct()
  {
  }

  public static function getInstance(): WsArgumentsHost
  {
    if (!self::$instance)
    {
      self::$instance = new WsArgumentsHost();
    }

    return self::$instance;
  }

  /**
   * @inheritDoc
   */
  public function getData(): mixed
  {
    // TODO: Implement getData() method.
    return null;
  }

  /**
   * @inheritDoc
   */
  public function getClient(): mixed
  {
    // TODO: Implement getClient() method.
    return null;
  }

  /**
   * @return array
   */
  public function getArgs(): array
  {
    return [$this->getData(), $this->getClient()];
  }
}