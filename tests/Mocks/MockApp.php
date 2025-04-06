<?php

namespace tests\Mocks;

use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Assegai\Core\Interfaces\IPipeTransform;
use Psr\Log\LoggerInterface;

/**
 * A mock implementation of the `AppInterface` interface.
 *
 * @package tests\Mocks
 */
class MockApp implements AppInterface
{
  /**
   * @var mixed $config The configuration properties.
   */
  protected mixed $config = null;
  /**
   * @var IPipeTransform[] $pipes The list of pipes.
   */
  protected array $pipes = [];
  /**
   * @var IAssegaiInterceptor[] $interceptors The list of interceptors.
   */
  protected array $interceptors = [];
  /**
   * @var ExceptionFilterInterface[] $filters The list of filters
   */
  protected array $filters = [];

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
  public function useGlobalFilters(ExceptionFilterInterface|array|string $filters): AppInterface
  {
    $this->filters = array_merge($this->filters, (is_array($filters) ? $filters : [$filters]));
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