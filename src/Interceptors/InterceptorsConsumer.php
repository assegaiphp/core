<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\InterceptorException;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Injector;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use ReflectionClass;
use ReflectionException;
use ReflectionUnionType;

final class InterceptorsConsumer
{
  private static ?self $instance = null;
  private final function __construct()
  {}

  public static function getInstance(): self
  {
    if (! self::$instance )
    {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * @param array<int, IAssegaiInterceptor|string|array> $interceptors
   * @param ExecutionContext $context
   * @return callable[]
   * @throws ContainerException
   * @throws InterceptorException
   * @throws ReflectionException
   */
  public function intercept(array $interceptors, ExecutionContext $context): array
  {
    $callHandlers = [];
    foreach ($this->resolveInterceptors($interceptors) as $interceptor)
    {
      if ($result = $interceptor->intercept(context: $context))
      {
        if (is_callable($result))
        {
          $callHandlers[] = $result;
        }
      }
    }

    return $callHandlers;
  }

  /**
   * @param array<int, IAssegaiInterceptor|string|array> $interceptors
   * @return array<int, IAssegaiInterceptor>
   * @throws ContainerException
   * @throws InterceptorException
   * @throws ReflectionException
   */
  private function resolveInterceptors(array $interceptors): array
  {
    $resolvedInterceptors = [];

    foreach ($interceptors as $interceptor) {
      if (is_array($interceptor)) {
        $resolvedInterceptors = [...$resolvedInterceptors, ...$this->resolveInterceptors($interceptor)];
        continue;
      }

      if ($interceptor instanceof IAssegaiInterceptor) {
        $resolvedInterceptors[] = $interceptor;
        continue;
      }

      if (is_string($interceptor)) {
        $resolvedInterceptors[] = $this->resolveInterceptor($interceptor);
        continue;
      }

      throw new InterceptorException(isRequestError: false);
    }

    return $resolvedInterceptors;
  }

  /**
   * @param class-string<IAssegaiInterceptor> $interceptorClass
   * @return IAssegaiInterceptor
   * @throws ContainerException
   * @throws InterceptorException
   * @throws ReflectionException
   */
  private function resolveInterceptor(string $interceptorClass): IAssegaiInterceptor
  {
    $injector = Injector::getInstance();

    try {
      $resolved = $injector->resolve($interceptorClass);

      if ($resolved instanceof IAssegaiInterceptor) {
        return $resolved;
      }
    } catch (ContainerException) {
      // Fall back to direct construction for simple interceptor classes.
    }

    $reflectionClass = new ReflectionClass($interceptorClass);

    if (!$reflectionClass->isInstantiable()) {
      throw new InterceptorException(isRequestError: false);
    }

    $constructor = $reflectionClass->getConstructor();

    if (!$constructor || !$constructor->getParameters()) {
      $instance = $reflectionClass->newInstance();

      if ($instance instanceof IAssegaiInterceptor) {
        return $instance;
      }

      throw new InterceptorException(isRequestError: false);
    }

    $dependencies = [];

    foreach ($constructor->getParameters() as $parameter) {
      $type = $parameter->getType();

      if (!$type || $type instanceof ReflectionUnionType || $type->isBuiltin()) {
        $dependencies[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        continue;
      }

      $dependencies[] = $injector->resolve($type->getName());
    }

    $instance = $reflectionClass->newInstanceArgs($dependencies);

    if (!$instance instanceof IAssegaiInterceptor) {
      throw new InterceptorException(isRequestError: false);
    }

    return $instance;
  }
}
