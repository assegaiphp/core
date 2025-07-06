<?php

namespace Assegai\Core\Queues;

use Assegai\Core\Queues\Interfaces\QueueInterface;
use Assegai\Core\Queues\Interfaces\QueueProcessResultInterface;

/**
 * Abstract class AbstractQueue
 *
 * Provides a base implementation for a queue system.
 * @template T
 * @template-extends QueueProcessResultInterface<T>
 */
abstract class AbstractQueue implements QueueInterface
{
  /**
   * @var string The name of the queue.
   */
  protected string $name;

  /**
   * @var T[] An array to hold the jobs in the queue.
   */
  protected array $queue = [];

  /**
   * @inheritDoc
   */
  public function add(object $job, object|array|null $options = null): void
  {
    $this->queue[] = $job;
  }

  /**
   * @inheritDoc
   */
  public function getTotalJobs(): int
  {
    return count($this->queue);
  }

  /**
   * @inheritDoc
   */
  public function getName(): string
  {
    return $this->name;
  }
}