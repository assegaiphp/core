<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\InterceptorException;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Attribute;
use ReflectionClass;
use ReflectionException;

#[Attribute]
class UseInterceptors
{
  /** @var IAssegaiInterceptor[] $interceptorsList */
  public readonly array $interceptorsList;

  /**
   * @param IAssegaiInterceptor[]|string[]|string|IAssegaiInterceptor $interceptors
   * @throws InterceptorException
   * @throws ReflectionException
   */
  public function __construct(protected readonly array|string|IAssegaiInterceptor $interceptors)
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