<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\ExecutionContext;
use Assegai\Core\Interfaces\IAssegaiInterceptor;

class EmptyResultInterceptor implements IAssegaiInterceptor
{
  public function __construct(public readonly int $code = 404)
  {
  }

  public function intercept(ExecutionContext $context): ?callable
  {
    $code = $this->code;

    return function (ExecutionContext $context) use ($code) {
      $response = $context->switchToHttp()->getResponse();
      $response->setBody([]);

      if (empty($response->getBody()))
      {
        $response->setStatus($code);
      }

      return $context;
    };
  }
}