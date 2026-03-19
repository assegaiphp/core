<?php

namespace Assegai\Core\Queues\Attributes;

use Attribute;

/**
 * Marks an injectable class as the processor for a named queue connection.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class QueueProcessor
{
  /**
   * @param string $path The queue path in driver.connection form.
   * @param string $method The processor method to invoke for each job.
   */
  public function __construct(
    public string $path,
    public string $method = 'process',
  )
  {
  }
}
