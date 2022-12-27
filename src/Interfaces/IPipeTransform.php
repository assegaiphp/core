<?php

namespace Assegai\Core\Interfaces;

use stdClass;

interface IPipeTransform
{
  /**
   * @param mixed $value
   * @param array|stdClass|null $metaData
   * @return mixed
   */
  public function transform(mixed $value, null|array|stdClass $metaData = null): mixed;
}