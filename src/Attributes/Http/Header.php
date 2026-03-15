<?php

namespace Assegai\Core\Attributes\Http;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION|Attribute::IS_REPEATABLE)]
class Header
{
  /**
   * @param string $key
   * @param string $value
   * @param bool $replace
   * @param int|null $statusCode
   */
  public function __construct(
    public readonly string $key,
    public readonly string $value,
    public readonly bool $replace = true,
    public readonly ?int $statusCode = null,
  ) {}
}
