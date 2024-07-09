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
    $templatePath = Paths::join(Paths::getViewDirectory(), $templateUrl . '.php');
    try {
      if ($this->component) {
        $componentReflection = new ReflectionClass($this->component);
        $componentAttrReflections = $componentReflection->getAttributes(Component::class);

        if (!$componentAttrReflections) {
          throw new RenderingException(message: "Missing Component attribute for {$this->component}");
        }

        foreach ($componentAttrReflections as $componentAttrReflection) {
          $this->componentAttribute = $componentAttrReflection->newInstance();
          $componentPath = dirname($componentReflection->getFileName());
          $templatePath = Paths::join($componentPath, $this->componentAttribute->templateUrl);
        }

        $data = get_object_vars($this->getComponent());
      } else {
        $this->componentAttribute = null;
      }

      $this->templateUrl = $templatePath;
      $this->data = $data;
      $this->props = is_array($props) ? ViewProperties::fromArray($props) : $props;

      if (! file_exists($this->templateUrl) ) {
        throw new RenderingException(message: 'Failed to open file at ' . $this->templateUrl);
      }
    } catch (ReflectionException $exception) {
      die(new HttpException($exception->getMessage()));
    }
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