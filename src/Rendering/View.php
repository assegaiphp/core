<?php

namespace Assegai\Core\Rendering;

use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Util\Paths;

/**
 *
 */
class View
{
  public readonly string $templateUrl;

  /**
   * @param string $templateUrl
   * @param array $props
   * @throws RenderingException
   */
  public final function __construct(
    string $templateUrl,
    public readonly array $props = []
  )
  {
    $this->templateUrl = Paths::join(Paths::getViewDirectory(), $templateUrl . '.php');

    if (! file_exists($this->templateUrl) )
    {
      throw new RenderingException(message: 'Failed to open file at ' . $this->templateUrl);
    }
  }
}