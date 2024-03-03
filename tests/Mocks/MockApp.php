<?php

namespace tests\Mocks;

use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\IPipeTransform;
use Psr\Log\LoggerInterface;

/**
 * A mock implementation of the `AppInterface` interface.
 */
class MockApp implements AppInterface
{
  protected mixed $config = null;
  protected array $pipes = [];
  protected ?LoggerInterface $logger = null;

  /**
   * @inheritDoc
   */
  public function configure(mixed $config = null): AppInterface
  {
    $this->config = $config;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function useGlobalPipes(IPipeTransform|array $pipes): AppInterface
  {
    $this->pipes = array_merge($this->pipes, (is_array($pipes) ? $pipes : [$pipes]));
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function setLogger(LoggerInterface $logger): AppInterface
  {
    $this->logger = $logger;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function run(): void
  {
    // Do nothing
  }
}