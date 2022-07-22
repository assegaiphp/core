<?php

namespace Assegai\Core\RPC;

use Assegai\Core\Interfaces\IRpcArgumentsHost;

class RpcArgumentsHost implements IRpcArgumentsHost
{
  protected static ?RpcArgumentsHost $instance = null;

  private final function __construct()
  {
  }

  public static function getInstance(): RpcArgumentsHost
  {
    if (!self::$instance)
    {
      self::$instance = new RpcArgumentsHost();
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
  public function getContext(): mixed
  {
    // TODO: Implement getContext() method.
    return null;
  }

  /**
   * @return array
   */
  public function getArgs(): array
  {
    return [$this->getData(), $this->getContext()];
  }
}