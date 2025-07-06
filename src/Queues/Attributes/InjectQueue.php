<?php

namespace Assegai\Core\Queues\Attributes;

use Assegai\Core\Queues\Interfaces\QueueInterface;
use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class InjectQueue
{
  public QueueInterface $queue;

  /**
   * InjectQueue constructor.
   *
   * @param string $name The name of the queue to inject.
   */
  public function __construct(
    public string $name = ''
  ) {}
}