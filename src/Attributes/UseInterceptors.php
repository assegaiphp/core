<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Attribute;

#[Attribute]
class UseInterceptors
{
  /**
   * @param IAssegaiInterceptor[]|string[]|string|IAssegaiInterceptor $interceptors
   */
  public function __construct(public readonly array|string|IAssegaiInterceptor $interceptors)
  {
  }
}