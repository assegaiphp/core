<?php

namespace tests\Mocks;

use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Assegai\Core\Interfaces\IPipeTransform;
use Psr\Log\LoggerInterface;

/**
 * A mock implementation of the `AppInterface` interface.
 */
class MockApp implements AppInterface
{
  /**
   * The configuration properties.
   *
   * @var mixed $config
   */
  protected mixed $config = null;
  /**
   * The list of pipes.
   *
   * @var IPipeTransform[] $pipes
   */
  protected array $pipes = [];
  /**
   * The list of interceptors.
   *
   * @var IAssegaiInterceptor[] $interceptors
   */
  protected array $interceptors = [];

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

  public function useGlobalInterceptors(IAssegaiInterceptor|array|string $interceptors): AppInterface
  {
    $this->interceptors = array_merge($this->interceptors, (is_array($interceptors) ? $interceptors : [$interceptors]));
    return $this;
  }
}