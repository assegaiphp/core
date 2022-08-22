<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Enumerations\EventChannel;
use Assegai\Core\Events\Event;
use Closure;

/**
 *
 */
interface IEventBroadcaster
{
  /**
   * @param EventChannel $channel
   * @param Event $event
   * @return void
   */
  public static function broadcast(EventChannel $channel, Event $event): void;

  /**
   * @param EventChannel $channel
   * @param Closure $handler
   * @return void
   */
  public static function subscribe(EventChannel $channel, Closure $handler): void;

  /**
   * @param EventChannel $channel
   * @return void
   */
  public static function clearChannel(EventChannel $channel): void;
}