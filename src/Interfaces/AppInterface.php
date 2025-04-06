<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Psr\Log\LoggerInterface;

interface AppInterface
{
  /**
   * Sets the app configuration to the given config properties.
   *
   * @param mixed|null $config The configuration properties.
   * @return self
   */
  public function configure(mixed $config = null): self;

  /**
   * Specifies a list of pipes that should be used by the `App` instance.
   *
   * @param IPipeTransform|array $pipes A list of pipes to be used by the `App` instance.
   * @return static The current `App` instance.
   */
  public function useGlobalPipes(IPipeTransform|array $pipes): self;

  /**
   * Specifies a list of interceptors that should be used by the `App` instance.
   *
   * @param IAssegaiInterceptor|string|array $interceptors A list of interceptors to be used by the `App` instance.
   * @return static The current `App` instance.
   */
  public function useGlobalInterceptors(IAssegaiInterceptor|string|array $interceptors): self;

  /**
   * @param ExceptionFilterInterface|class-string|array<class-string|ExceptionFilterInterface> $filters
   * @return self
   */
  public function useGlobalFilters(ExceptionFilterInterface|string|array $filters): self;

  /**
   * Sets a logger instance that should be user by the `App` instance.
   *
   * @param LoggerInterface $logger The logger instance to be used by the `App` instance.
   * @return self
   */
  public function setLogger(LoggerInterface $logger): self;

  /**
   * Runs the current application.
   *
   * @return void
   */
  public function run(): void;
}