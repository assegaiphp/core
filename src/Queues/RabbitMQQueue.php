<?php

namespace Assegai\Core\Queues;

use AMQPConnection;
use Assegai\Core\Queues\Exceptions\QueueException;
use Assegai\Core\Queues\Interfaces\QueueInterface;
use Assegai\Core\Queues\Interfaces\QueueProcessResultInterface;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class RabbitMQQueue
 *
 * Represents a RabbitMQ queue implementation.
 * @extends AbstractQueue
 */
class RabbitMQQueue implements QueueInterface
{
  public const int DEFAULT_PORT = 5672;

  protected AMQPStreamConnection $connection;
  protected AMQPChannel $channel;
  protected int $totalJobs = 0;

  /**
   * RabbitMQQueue constructor.
   *
   * @param string $name The name of the queue.
   * @param string|null $host The host of the RabbitMQ server.
   * @param int|null $port The port of the RabbitMQ server.
   * @param string|null $username The username for RabbitMQ authentication.
   * @param string|null $password The password for RabbitMQ authentication.
   * @param string|null $vhost The virtual host for RabbitMQ.
   * @throws QueueException
   */
  public function __construct(
    protected string $name,
    protected ?string $host = null,
    protected ?int $port = null,
    protected ?string $username = null,
    protected ?string $password = null,
    protected ?string $vhost = null,
    protected bool $passive = false,
    protected bool $durable = true,
    protected bool $exclusive = false,
    protected bool $autoDelete = false
  )
  {
    try {
      $this->connection = new AMQPStreamConnection(
        $this->host,
        $this->port ?? self::DEFAULT_PORT,
        $this->username,
        $this->password,
        $this->vhost ?? '/'
      );

      $this->connection->channel();
      $this->channel = $this->connection->channel();
      $this->channel->queue_declare($this->name, $this->passive, $this->durable, $this->exclusive, $this->autoDelete);
    } catch (Exception $exception) {
      throw new QueueException($exception->getMessage(), $exception->getCode(), $exception);
    }
  }

  /**
   * RabbitMQQueue destructor.
   *
   * Closes the channel and connection when the object is destroyed.
   */
  public function __destruct()
  {
    $this->channel->close();
    $this->connection->close();
  }

  /**
   * @inheritDoc
   */
  public function process(callable $callback): QueueProcessResultInterface
  {
    $this->channel->basic_consume($this->name, '', false, false, false, false, $callback);

    try {
      $this->channel->consume();
      $this->totalJobs--;
      $result = new QueueProcessResult(
        ['channelId' => $this->channel->getChannelId()],
      );
    } catch (Exception $exception) {
      $result = new QueueProcessResult(
        errors: [new QueueException("Queue processing failed!", $exception->getCode(), $exception)]
      );
    }

    return $result;
  }

  /**
   * @inheritDoc
   * @throws QueueException
   */
  public function add(object $job, object|array|null $options = null): void
  {
    $messageProperties = [
      'content_type' => 'application/json',
      'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // Make message persistent
    ];
    echo "Adding job to queue '{$this->name}': " . json_encode($job) . PHP_EOL;
    $message = new AMQPMessage(json_encode($job) ?: throw new QueueException('Failed to convert Job to JSON string.'), $messageProperties);
    $this->channel->basic_publish($message, '', $this->name);
    $this->totalJobs++;
  }

  /**
   * @inheritDoc
   */
  public function getName(): string
  {
    return $this->name;
  }

  /**
   * @inheritDoc
   */
  public function getTotalJobs(): int
  {
    return $this->totalJobs;
  }
}