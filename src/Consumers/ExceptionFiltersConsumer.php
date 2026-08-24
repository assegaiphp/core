<?php

namespace Assegai\Core\Consumers;

use Assegai\Core\ArgumentsHost;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Filters\ExceptionFilterBinding;
use Assegai\Core\Exceptions\Filters\ExceptionFilterMetadata;
use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Assegai\Core\Injector;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Resolves and executes exception filters in precedence order.
 */
final readonly class ExceptionFiltersConsumer
{
  public function __construct(private Injector $injector)
  {
  }

  /**
   * Executes the first filter whose exception metadata matches the throwable.
   *
   * @param array<int, ExceptionFilterBinding> $bindings
   * @throws ContainerException
   * @throws ReflectionException
   */
  public function handle(Throwable $throwable, ArgumentsHost $host, array $bindings): bool
  {
    foreach ($bindings as $binding) {
      $filter = $this->resolveFilter($binding->filter);
      $exceptionTypes = $binding->exceptionTypes;

      if ($exceptionTypes === []) {
        $exceptionTypes = ExceptionFilterMetadata::exceptionTypes(new ReflectionClass($filter));
      }

      if ($exceptionTypes === []) {
        $exceptionTypes = [Throwable::class];
      }

      foreach ($exceptionTypes as $exceptionType) {
        if (!is_a($throwable, $exceptionType)) {
          continue;
        }

        $filter->catch($throwable, $host);
        return true;
      }
    }

    return false;
  }

  /**
   * @param class-string<ExceptionFilterInterface>|ExceptionFilterInterface $definition
   * @throws ContainerException
   * @throws ReflectionException
   */
  private function resolveFilter(string|ExceptionFilterInterface $definition): ExceptionFilterInterface
  {
    if ($definition instanceof ExceptionFilterInterface) {
      return $definition;
    }

    try {
      $resolved = $this->injector->resolve($definition);

      if ($resolved instanceof ExceptionFilterInterface) {
        return $resolved;
      }
    } catch (ContainerException) {
      // Lightweight filters without dependencies can still be used by class name.
    }

    $reflection = new ReflectionClass($definition);

    if (!$reflection->implementsInterface(ExceptionFilterInterface::class)) {
      throw new ContainerException("$definition must implement ExceptionFilterInterface");
    }

    $constructor = $reflection->getConstructor();

    if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
      throw new ContainerException(
        "$definition could not be resolved. Register it as a provider or pass a configured instance."
      );
    }

    /** @var ExceptionFilterInterface */
    return $reflection->newInstance();
  }
}
