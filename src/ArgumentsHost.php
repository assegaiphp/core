<?php /** @noinspection ALL */

namespace Assegai\Core;

use Assegai\Core\Enumerations\Http\ContextType;
use Assegai\Core\Http\HttpArgumentsHost;
use Assegai\Core\Interfaces\IArgumentHost;
use Assegai\Core\Interfaces\IHttpArgumentsHost;
use Assegai\Core\Interfaces\IRpcArgumentsHost;
use Assegai\Core\Interfaces\IWsArgumentsHost;
use Assegai\Core\RPC\RpcArgumentsHost;
use Assegai\Core\WebSockets\WsArgumentsHost;

/**
 * Class ArgumentsHost.
 * @package Assegai\Core
 */
class ArgumentsHost implements IArgumentHost
{
  public function __construct(protected ContextType $contextType = ContextType::HTTP)
  {
  }

  /**
   * Returns the context type.
   *
   * @return ContextType Returns the context type.
   */
  public function getType(): ContextType
  {
    return $this->contextType;
  }

  /**
   * Returns the arguments passed to the handler.
   *
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

  /**
   * Switches to RPC context.
   *
   * @return IRpcArgumentsHost Returns the RPC arguments host.
   */
  public function switchToRpc(): IRpcArgumentsHost
  {
    $this->contextType = ContextType::RPC;
    return RpcArgumentsHost::getInstance();
  }

  /**
   * Switches to WebSockets context.
   *
   * @return IWsArgumentsHost Returns the WebSockets arguments host.
   */
  public function switchToWs(): IWsArgumentsHost
  {
    $this->contextType = ContextType::WEB_SOCKETS;
    return WsArgumentsHost::getInstance();
  }

  /**
   * Switches to HTTP context.
   *
   * @return IHttpArgumentsHost Returns the HTTP arguments host.
   */
  public function switchToHttp(): IHttpArgumentsHost
  {
    $this->contextType = ContextType::HTTP;
    return HttpArgumentsHost::getInstance();
  }
}