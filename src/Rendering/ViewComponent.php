<?php

namespace Assegai\Core\Rendering;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Exceptions\FileException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\ModuleManager;
use Assegai\Core\Rendering\Interfaces\RendererInterface;
use Assegai\Core\Util\Paths;
use ReflectionClass;
use Stringable;

/**
 * Class ViewComponent. This class is for the view component.
 */
abstract class ViewComponent implements RendererInterface, Stringable
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

  /**
   * @param string $selector
   * @return string
   */
  public static function getTemplateContentBySelector(string $selector): string
  {
    $selectedAttribute = ModuleManager::getInstance()->getDeclaredAttributes()[$selector];
    $selectedReflection = ModuleManager::getInstance()->getDeclaredReflections()[$selector];

    if (!$selectedAttribute || !$selectedReflection)
    {
      die(new RenderingException("Invalid selector"));
    }

    if (!$selectedAttribute->templateUrl)
    {
      return $selectedAttribute->template;
    }

    $componentPath = Paths::join($selectedReflection->getFileName(), $selectedAttribute->templateUrl);

    if (!file_exists($componentPath))
    {
      die(new NotFoundException($componentPath));
    }

    $content = file_get_contents($componentPath);

    if (!$content)
    {
      die(new FileException("Failed to open file {$content}"));
    }

    return $content;
  }

  /**
   * Returns the path to the template file identified by the given selector.
   *
   * @param string $selector The selector for the template file.
   * @return string The path to the template file, or false if an error occurs.
   * @throws RenderingException If the given selector is invalid.
   * @throws NotFoundException If the template file does not exist.
   */
  public static function getTemplatePathBySelector(string $selector): string
  {
    $selectedAttribute = ModuleManager::getInstance()->getDeclaredAttributes()[$selector];
    $selectedReflection = ModuleManager::getInstance()->getDeclaredReflections()[$selector];

    if (!$selectedAttribute || !$selectedReflection)
    {
      throw new RenderingException("Invalid selector");
    }

    $path = Paths::join($selectedReflection->getFileName(), $selectedAttribute->templateUrl);

    if (!file_exists($path))
    {
      throw new NotFoundException($path);
    }

    return $path;
  }

  /**
   * @inheritDoc
   */
  public final function __toString(): string
  {
    return $this->render() ?? '';
  }
}