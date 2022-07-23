<?php

namespace Assegai\Core\Interfaces;

use stdClass;

interface IPipeTransform
{
  public function transform(mixed $value, null|array|stdClass $metaData = null): mixed;
}