<?php

namespace Assegai\Core\Attributes;

use Assegai\Core\Files\File;
use Assegai\Core\Files\TempFile;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Interfaces\IPipeTransform;
use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class UploadedFile
{
  /**
   * @var object|array
   */
  public readonly object|array $value;

  /**
   * @param array|IPipeTransform|null $pipeTransform
   */
  public function __construct(public readonly array|IPipeTransform|null $pipeTransform = null)
  {
    $this->value = Request::getInstance()->getFile();
  }
}