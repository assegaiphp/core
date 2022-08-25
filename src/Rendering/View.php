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
  readonly ViewProperties $props;

  /**
   * @param string $templateUrl
   * @param array $data
   * @param ViewProperties|array $props
   * @throws RenderingException
   */
  public final function __construct(
    string $templateUrl,
    public readonly array $data = [],
    ViewProperties|array $props = new ViewProperties(),
  )
  {
    $this->templateUrl = Paths::join(Paths::getViewDirectory(), $templateUrl . '.php');
    $this->props = is_array($props) ? ViewProperties::fromArray($props) : $props;

    if (! file_exists($this->templateUrl) )
    {
      throw new RenderingException(message: 'Failed to open file at ' . $this->templateUrl);
    }
  }
}