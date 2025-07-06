<?php

namespace Assegai\Core\Queues;

use Assegai\Core\Queues\Interfaces\QueueProcessResultInterface;
use Throwable;

/**
 * Class QueueProcessResult
 *
 * Represents the result of processing a job in a queue.
 * Implements the QueueProcessResultInterface.
 * @template T
 */
class QueueProcessResult implements QueueProcessResultInterface
{
  /**
   * QueueProcessResult constructor.
   *
   * @param mixed $data The result data of processing the job.
   * @param array $errors An array of errors encountered during processing.
   * @param object<T>|null $job The job that was processed, or null if no job was processed.
   */
  public function __construct(
    protected mixed $data = null,
    protected array $errors = [],
    protected ?object $job = null
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function getData(): mixed
  {
    return $this->data;
  }

  /**
   * @inheritDoc
   */
  public function isOk(): bool
  {
    return !$this->getErrors();
  }

  /**
   * @inheritDoc
   */
  public function isError(): bool
  {
    return !empty($this->errors);
  }

  /**
   * @inheritDoc
   */
  public function getErrors(): array
  {
    return $this->errors;
  }

  /**
   * @inheritDoc
   */
  public function getNextError(): ?Throwable
  {
    return $this->errors[0] ?? null;
  }

  /**
   * @inheritDoc
   */
  public function getJob(): ?object
  {
    return $this->job;
  }
}