<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * An attribute that marks a class as an AssegaiPHP component and provides configuration metadata that determines
 * how the component should be processed, instantiated and used at runtime.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Component
{
  /**
   * Constructs a component attribute.
   *
   * @param string $selector The CSS selector that identifies this component in a template.
   * @param string[]|null $providers Defines the set of injectable objects that are visible to its view DOM children.
   * @param string|null $moduleId The module ID of the module that contains the component. The component must be able
   * to resolve relative URLs for templates and styles.
   * @param string|null $templateUrl The URL of an external file containing an AssegaiPHP component template.
   * @param string|null $template An inline template for an AssegaiPHP component. If provided, do not supply a
   * template file using templateUrl.
   * @param string[]|null $styleUrls One or more relative paths or absolute URLs for files containing CSS stylesheets
   * to use in this component.
   * @param string[]|null $styles One or more inline CSS stylesheets to use in this component.
   * @param array|null $scriptUrls One or more relative paths or absolute URLs for files containing JavaScript scripts
   * @param array|null $scripts One or more inline JavaScript scripts
   */
  public function __construct(
    public string $selector,
    public ?array  $providers = [],
    public ?string $moduleId = null,
    public ?string $templateUrl = null,
    public ?string $template = null,
    public ?array  $styleUrls = [],
    public ?array  $styles = [],
    public ?array  $scriptUrls = [],
    public ?array  $scripts = []
  )
  {
  }
}