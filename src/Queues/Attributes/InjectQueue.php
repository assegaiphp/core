<?php

namespace Assegai\Core\Queues\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class InjectQueue
{
  /**
   * InjectQueue constructor.
   *
   * @param string $path The path to the queue configuration, e.g., 'redis.default'.
   */
  public function __construct(
    public string $path = ''
  ) {
  }
}
