<?php

namespace Assegai\Core\Pipes;

use Assegai\Core\Interfaces\IPipeTransform;
use stdClass;

class ParseFilePipe implements IPipeTransform
{

  /**
   * @inheritDoc
   */
  public function transform(mixed $value, array|stdClass|null $metaData = null): mixed
  {
    // TODO: Implement transform() method.
    return null;
  }
}