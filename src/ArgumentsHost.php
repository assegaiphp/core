<?php

namespace Assegai\Core;

use Assegai\Core\Enumerations\Http\ContextType;
use Assegai\Core\Http\HttpArgumentsHost;
use Assegai\Core\Interfaces\IHttpArgumentsHost;
use Assegai\Core\Interfaces\IRpcArgumentsHost;
use Assegai\Core\Interfaces\IWsArgumentsHost;
use Assegai\Core\RPC\RpcArgumentsHost;
use Assegai\Core\WebSockets\WsArgumentsHost;

class ArgumentsHost
{
  public function __construct(protected ContextType $contextType = ContextType::HTTP)
  {
  }

  public function getType(): ContextType
  {
    return $this->contextType;
  }

  /**
   * @return array Returns an array of arguments passed to the handler.
   */
  public function getArgs(): array
  {
    return match($this->contextType) {
      ContextType::RPC => $this->switchToRpc()->getArgs(),
      ContextType::WEB_SOCKETS => $this->switchToWs()->getArgs(),
      default => $this->switchToHttp()->getArgs()
    };
  }

  /**
   * @param int $index The index of the argument to be retrieved.
   * @return array Returns a particular argument by index.
   */
  public function getArgsByIndex(int $index): array
  {
    return $this->getArgs[$index] ?? [];
  }

  public function switchToRpc(): IRpcArgumentsHost
  {
    $this->contextType = ContextType::RPC;
    return RpcArgumentsHost::getInstance();
  }

  private function switchToWs(): IWsArgumentsHost
  {
    $this->contextType = ContextType::WEB_SOCKETS;
    return WsArgumentsHost::getInstance();
  }

  private function switchToHttp(): IHttpArgumentsHost
  {
    $this->contextType = ContextType::HTTP;
    return HttpArgumentsHost::getInstance();
  }
}