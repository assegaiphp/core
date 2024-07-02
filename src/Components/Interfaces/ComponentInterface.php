<?php

namespace Assegai\Core\Components\Interfaces;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Rendering\Interfaces\RendererInterface;
use Stringable;

/**
 * Interface ComponentInterface. This interface is used to define the methods that a component class must implement.
 *
 * @package Assegai\Core\Components\Interfaces
 */
interface ComponentInterface extends RendererInterface, Stringable
{
  /**
   * Returns the component attribute.
   *
   * @return Component The component attribute.
   * @throws RenderingException If the component attribute is not found.
   */
  public function getAttribute(): Component;
}