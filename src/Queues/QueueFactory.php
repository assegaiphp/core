<?php

namespace Assegai\Core\Queues;

use Assegai\Common\Interfaces\Queues\QueueInterface;
use Assegai\Core\Attributes\Injectable;
use InvalidArgumentException;

#[Injectable]
final class QueueFactory
{
  /** @var array<string, QueueInterface<object>> */
  private array $connections = [];

  /**
   * Resolves one configured queue for the lifetime of the application graph.
   *
   * Driver creation must remain configuration-only. Transport implementations
   * establish their broker connection when an operation first needs it.
   *
   * @return QueueInterface<object>
   */
  public function connection(string $path): QueueInterface
  {
    $path = trim($path);

    if (isset($this->connections[$path])) {
      return $this->connections[$path];
    }

    [$driver, $name] = explode('.', $path, 2) + [null, null];

    if (!is_string($driver) || $driver === '' || !is_string($name) || $name === '') {
      throw new InvalidArgumentException("Invalid queue path '$path'. Expected format: 'driver.name'.");
    }

    $queueConfig = config("queues.connections.$path");

    if (!is_array($queueConfig)) {
      throw new InvalidArgumentException("Queue configuration for '$path' not found.");
    }

    $queueConfig['name'] ??= $name;
    $driverClass = config("queues.drivers.$driver");

    if (!is_string($driverClass) || !class_exists($driverClass)) {
      throw new InvalidArgumentException("Queue driver '$driver' is not configured or does not exist.");
    }

    if (!is_subclass_of($driverClass, QueueInterface::class)) {
      throw new InvalidArgumentException("Queue driver '$driver' must implement the QueueInterface.");
    }

    return $this->connections[$path] = $driverClass::create($queueConfig);
  }
}
