<?php

namespace Assegai\Core\Rendering;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Util\Paths;
use ReflectionClass;
use ReflectionException;

/**
 * Class View represents a view.
 *
 * @package Assegai\Core\Rendering
 */
class View
{
  /**
   * The URL of the template.
   *
   * @var string
   */
  public readonly string $templateUrl;
  /**
   * The component attribute.
   *
   * @var Component|null
   */
  private ?Component $componentAttribute;
  /**
   * The view properties.
   *
   * @var ViewProperties
   */
  readonly ViewProperties $props;
  /**
   * The data to render.
   *
   * @var array<string, mixed> $data
   */
  readonly array $data;

  /**
   * @param string $templateUrl
   * @param array $data
   * @param ViewProperties|array $props
   * @param string|null $component
   * @throws RenderingException
   */
  public final function __construct(
    string $templateUrl,
    array $data = [],
    ViewProperties|array $props = new ViewProperties(),
    public ?string $component = null
  )
  {
    try {
      $templatePath = $this->resolveViewTemplatePath($templateUrl);

      if ($this->component) {
        $componentReflection = new ReflectionClass($this->component);
        $componentAttrReflections = $componentReflection->getAttributes(Component::class);

        if (!$componentAttrReflections) {
          throw new RenderingException(message: "Missing Component attribute for {$this->component}");
        }

        foreach ($componentAttrReflections as $componentAttrReflection) {
          $this->componentAttribute = $componentAttrReflection->newInstance();
          $templatePath = $this->resolveComponentTemplatePath($componentReflection, $this->componentAttribute);
        }

        $data = get_object_vars($this->getComponent());
      } else {
        $this->componentAttribute = null;
      }

      $this->templateUrl = $templatePath;
      $this->data = $data;
      $this->props = is_array($props) ? ViewProperties::fromArray($props) : $props;
    } catch (ReflectionException $exception) {
      throw new HttpException($exception->getMessage(), previous: $exception);
    }
  }

  /**
   * @throws RenderingException
   */
  private function resolveViewTemplatePath(string $templateUrl): string
  {
    $viewDirectory = realpath(Paths::getViewDirectory());

    if (!is_string($viewDirectory) || !is_dir($viewDirectory)) {
      throw new RenderingException(message: 'Failed to resolve the views directory.');
    }

    return $this->resolveTemplatePathWithinDirectory(
      $viewDirectory,
      Paths::join($viewDirectory, $templateUrl . '.php'),
    );
  }

  /**
   * @throws RenderingException
   */
  private function resolveComponentTemplatePath(ReflectionClass $componentReflection, Component $componentAttribute): string
  {
    $componentFilename = $componentReflection->getFileName();

    if (!is_string($componentFilename) || $componentFilename === '') {
      throw new RenderingException(message: 'Failed to resolve the component path.');
    }

    $componentDirectory = realpath(dirname($componentFilename));

    if (!is_string($componentDirectory) || !is_dir($componentDirectory)) {
      throw new RenderingException(message: 'Failed to resolve the component directory.');
    }

    return $this->resolveTemplatePathWithinDirectory(
      $componentDirectory,
      Paths::join($componentDirectory, (string) $componentAttribute->templateUrl),
    );
  }

  /**
   * @throws RenderingException
   */
  private function resolveTemplatePathWithinDirectory(string $baseDirectory, string $candidatePath): string
  {
    $resolvedPath = realpath($candidatePath);

    if (!is_string($resolvedPath) || !is_file($resolvedPath)) {
      throw new RenderingException(message: 'Failed to open file at ' . $candidatePath);
    }

    if (!$this->isPathInsideDirectory($resolvedPath, $baseDirectory)) {
      throw new RenderingException(message: 'Template path must stay within ' . $baseDirectory);
    }

    return $resolvedPath;
  }

  private function isPathInsideDirectory(string $path, string $directory): bool
  {
    $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($path, $directory);
  }

  /**
   * Returns the component attribute.
   *
   * @return Component|null The component attribute.
   */
  public function getComponent(): ?Component
  {
    return $this->componentAttribute;
  }
}
