<?php

namespace Assegai\Core\Events;

use Assegai\Core\Enumerations\EventChannel;
use Assegai\Core\Interfaces\IEventBroadcaster;
use Closure;

/**
 *
 */
class EventManager implements IEventBroadcaster
{
  /**
   * @var array|array[]
   */
  private static array $eventQueues = [
    'APP_INIT_START' => [],
    'APP_INIT_FINISH' => [],
    'APP_LISTENING_START' => [],
    'APP_LISTENING_FINISH' => [],
    'MODULE_RESOLUTION_START' => [],
    'MODULE_RESOLUTION_FINISH' => [],
    'PROVIDER_RESOLUTION_START' => [],
    'PROVIDER_RESOLUTION_FINISH' => [],
    'CONTROLLER_RESOLUTION_START' => [],
    'CONTROLLER_RESOLUTION_FINISH' => [],
    'CONTROLLER_WILL_ACTIVATE' => [],
    'CONTROLLER_DID_ACTIVATE' => [],
    'HANDLER_WILL_ACTIVATE' => [],
    'HANDLER_DID_ACTIVATE' => [],
    'REQUEST_HANDLING_START' => [],
    'REQUEST_HANDLING_FINISH' => [],
    'RESPONSE_HANDLING_START' => [],
    'RESPONSE_HANDLING_FINISH' => [],
  ];

  /**
   * Constructs an EventManager instance.
   */
  private final function __construct()
  {}

  /**
   * @param EventChannel $channel
   * @param Event $event
   * @return void
   */
  public static function broadcast(EventChannel $channel, Event $event): void
  {
    foreach (self::$eventQueues[$channel->value] as $handler)
    {
      $handler($event);
    }
  }

  /**
   * @param EventChannel $channel
   * @param Closure $handler
   * @return void
   */
  public static function subscribe(EventChannel $channel, Closure $handler): void
  {
    self::$eventQueues[$channel->value][] = $handler;
  }

  /**
   * @param EventChannel $channel
   * @return void
   */
  public static function clearChannel(EventChannel $channel): void
  {
    self::$eventQueues[$channel->value] = [];
  }
}