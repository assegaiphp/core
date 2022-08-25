<?php

namespace Assegai\Core\Traits;

use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Rendering\View;
use Assegai\Core\Rendering\ViewProperties;

trait ViewRenderer
{
  /**
   * @throws RenderingException
   */
  public function view(string $templateURL, array $data = [], array|ViewProperties $props = []): View
  {
    $data = array_merge(get_object_vars($this), $data);

    return new View($templateURL, $data, $props);
  }
}