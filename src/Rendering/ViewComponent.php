<?php

namespace Assegai\Core\Rendering;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Interceptors\IRenderer;
use ReflectionClass;
use Stringable;

/**
 *
 */
abstract class ViewComponent implements IRenderer, Stringable
{
  /**
   * Constructs a ViewComponent object.
   * @throws RenderingException
   */
  public final function __construct()
  {
    $reflection = new ReflectionClass($this);
    $componentAttributes = $reflection->getAttributes(Component::class);

    if (!$componentAttributes)
    {
      throw new RenderingException("Invalid ViewComponent - Component Attribute not found");
    }

    # Process
  }

  public final function __toString()
  {
    return $this->render();
  }
}