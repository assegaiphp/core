<?php

namespace Assegai\Core\Queues\Attributes;

use Assegai\Core\Queues\Interfaces\QueueInterface;
use Assegai\Core\Queues\QueueFactory;
use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class InjectQueue
{
  public QueueInterface $queue;

  /**
   * InjectQueue constructor.
   *
   * @param string $path The path to the queue configuration, e.g., 'redis.default'.
   */
  public function __construct(
    public string $path = ''
  ) {
    $queueConfig = config("queues.$path");

    if (empty($queueConfig)) {
      throw new InvalidArgumentException("Queue configuration for '$path' not found.");
    }

    [$driver, $name] = explode('.', $path, 2) + [null, null];

    if (is_null($name)) {
      throw new InvalidArgumentException("Invalid queue path '$path'. Expected format: 'driver.name'.");
    }

    $queueFactory = QueueFactory::getInstance();
    $this->queue = $queueFactory->createQueue($driver, $name, $queueConfig);
  }
}