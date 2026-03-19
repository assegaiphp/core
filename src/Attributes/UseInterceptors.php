<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Exceptions\InterceptorException;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Attribute;

/**
 * An attribute that specifies which interceptors to run during the request/response lifecycle.
 *
 * @package Assegai\Core\Attributes
 */
#[Attribute]
readonly class UseInterceptors
{
  /** @var array<int, IAssegaiInterceptor|string> $interceptorsList */
  public array $interceptorsList;

  /**
   * The constructor for the UseInterceptors attribute.
   *
   * @param IAssegaiInterceptor[]|string[]|IAssegaiInterceptor|string $interceptors
   * @throws InterceptorException
   */
  public function __construct(protected array|string|IAssegaiInterceptor $interceptors)
  {
    $this->interceptorsList = $this->normalizeInterceptors($this->interceptors);
  }

  /**
   * @param IAssegaiInterceptor[]|string[]|IAssegaiInterceptor|string $interceptors
   * @return array<int, IAssegaiInterceptor|string>
   * @throws InterceptorException
   */
  private function normalizeInterceptors(array|string|IAssegaiInterceptor $interceptors): array
  {
    if (!is_array($interceptors)) {
      return [$interceptors];
    }

    $interceptorsList = [];

    foreach ($interceptors as $interceptor) {
      if (is_array($interceptor)) {
        $interceptorsList = [...$interceptorsList, ...$this->normalizeInterceptors($interceptor)];
      } elseif ($interceptor instanceof IAssegaiInterceptor || is_string($interceptor)) {
        $interceptorsList[] = $interceptor;
      } else {
        throw new InterceptorException(isRequestError: false);
      }
    }

    return $interceptorsList;
  }
}
