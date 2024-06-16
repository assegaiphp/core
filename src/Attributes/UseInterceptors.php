<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\InterceptorException;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Attribute;
use ReflectionClass;
use ReflectionException;

/**
 * An attribute that specifies which interceptors to run during the request/response lifecycle.
 *
 * @package Assegai\Core\Attributes
 */
#[Attribute]
readonly class UseInterceptors
{
  /** @var IAssegaiInterceptor[] $interceptorsList */
  public array $interceptorsList;

  /**
   * The constructor for the UseInterceptors attribute.
   *
   * @param IAssegaiInterceptor[]|string[]|IAssegaiInterceptor|string $interceptors
   * @throws InterceptorException If the interceptor is not instantiable.
   * @throws ReflectionException If the reflection class is not found.
   */
  public function __construct(protected array|string|IAssegaiInterceptor $interceptors)
  {
    $interceptorsList = [];

    if ($this->interceptors instanceof IAssegaiInterceptor)
    {
      $interceptorsList[] = $this->interceptors;
    }
    else if (is_string($this->interceptors))
    {
      $reflectionClass = new ReflectionClass($this->interceptors);

      if (! $reflectionClass->isInstantiable() )
      {
        throw new InterceptorException(isRequestError: false);
      }

      $interceptorsList[] = $reflectionClass->newInstance();
    }
    else
    {
      foreach ($this->interceptors as $interceptor)
      {
        if ($interceptor instanceof IAssegaiInterceptor)
        {
          $interceptorsList[] = $interceptor;
        }
        else if (is_string($interceptor))
        {
          $reflectionClass = new ReflectionClass($interceptor);

          if (! $reflectionClass->isInstantiable() )
          {
            throw new InterceptorException(isRequestError: false);
          }

          $interceptorsList[] = $reflectionClass->newInstance();
        }
      }
    }

    $this->interceptorsList = $interceptorsList;
  }
}