<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * An attribute that marks a class as an AssegaiPHP component and provides configuration metadata that determines
 * how the component should be processed, instantiated and used at runtime.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Component
{
  /**
   * Constructs a component attribute.
   *
   * @param array|null $providers Defines the set of injectable objects that are visible to its view DOM children.
   * @param string|null $moduleId The module ID of the module that contains the component. The component must be able 
   * to resolve relative URLs for templates and styles.
   * @param string|null $templateURL The relative path or absolute URL of a template file for an AssegaiPHP component.
   * If provided, do not supply an inline template using template.
   * @param string|null $template An inline template for an AssegaiPHP component. If provided, do not supply a
   * template file using templateUrl.
   * @param array|null $styleUrls One or more relative paths or absolute URLs for files containing CSS stylesheets
   * to use in this component.
   * @param array|null $styles One or more inline CSS stylesheets to use in this component.
   */
  public function __construct(
    public readonly ?array $providers = [],
    public readonly ?string $moduleId = null,
    public readonly ?string $templateURL = null,
    public readonly ?string $template = null,
    public readonly ?array $styleUrls = [],
    public readonly ?array $styles = [],
  )
  {
  }
}