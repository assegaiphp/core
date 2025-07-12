<?php

namespace Assegai\Core\Queues;

use Assegai\Core\Interfaces\SingletonInterface;
use Assegai\Core\Queues\Exceptions\QueueException;
use Assegai\Core\Queues\Interfaces\QueueInterface;
use InvalidArgumentException;
use Pheanstalk\Values\Timeout;

class QueueFactory implements SingletonInterface
{
  protected static ?QueueFactory $instance = null;

  public static function getInstance(): SingletonInterface
  {
    if (!self::$instance) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  private function __construct()
  {}

  /**
   * @param string $driver
   * @param string $name
   * @param array $config
   * @return QueueInterface
   * @throws QueueException
   */
  public function createQueue(string $driver, string $name, array $config): QueueInterface
  {
    switch ($driver) {
      case 'rabbitmq':
        return new RabbitMQQueue(
          $name,
          $config['host'] ?? null,
          $config['port'] ?? RabbitMQQueue::DEFAULT_PORT,
          $config['username'] ?? null,
          $config['password'] ?? null,
          $config['vhost'] ?? null,
          $config['passive'] ?? false,
          $config['durable'] ?? true,
          $config['exclusive'] ?? false,
          $config['auto_delete'] ?? false
        );
      // Add other drivers here as needed.
      case 'beanstalk':
        $connectionTimeout = $config['connection_timeout'] ?? null;
        $receiveTimeout = $config['receive_timeout'] ?? null;
        if ($connectionTimeout) {
          $connectionTimeout = new Timeout($connectionTimeout);
        }
        if ($receiveTimeout) {
          $receiveTimeout = new Timeout($receiveTimeout);
        }
        return new BeanstalkQueue(
          $name,
          $config['host'] ?? null,
          $config['port'] ?? BeanstalkQueue::DEFAULT_PORT,
          $connectionTimeout,
          $receiveTimeout
        );
      default:
        throw new InvalidArgumentException("Unsupported queue driver: $driver");
    }
  }
}