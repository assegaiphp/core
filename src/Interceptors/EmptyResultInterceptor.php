<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\ExecutionContext;

class EmptyResultInterceptor implements \Assegai\Core\Interfaces\IAssegaiInterceptor
{
  public function __construct(public readonly int $code = 404)
  {
  }

  public function intercept(ExecutionContext $context): ?callable
  {
    $code = $this->code;

    return function (ExecutionContext $context) use ($code) {
      $response = $context->switchToHttp()->getResponse();

      if (empty($response->getBody()))
      {
        $response->setStatus($code);
      }

      return $context;
    };
  }
}