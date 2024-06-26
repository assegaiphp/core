<?php

use Assegai\Core\Components\ComponentFactory;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Rendering\View;
use Assegai\Core\Rendering\ViewProperties;

if (!function_exists('json_is_valid') ) {
  /**
   * Returns true if the JSON string is valid, false otherwise.
   *
   * @param string $input The JSON string to validate
   * @return bool Returns true if the JSON string is valid, false otherwise.
   */
  function json_is_valid(string $input): bool
  {
    return json_validate($input);
  }
}

if (!function_exists('debug')) {
  /**
   * Print the given variables to the console or error log.
   *
   * @param mixed ...$variables The variables to print.
   * @return void
   */
  function debug(mixed ...$variables): void
  {
    foreach ($variables as $index => $variable) {
      error_log(sprintf("\e[0;33m%d\e[0m]\t-\t%s\n", $index, var_export($variable, true)));
    }
  }
}

if (!function_exists('debug_and_exit')) {
  /**
   * Dump the variables and exit.
   *
   * @param mixed ...$variables The variables to print.
   * @return never
   */
  function debug_and_exit(mixed ...$variables): never
  {
    debug(...$variables);
    exit(1);
  }
}

if (! function_exists('render') ) {
  function render(string $componentClass): ComponentInterface
  {
    return ComponentFactory::createComponent($componentClass);
  }
}

/**
 * Renders a view.
 *
 * @throws RenderingException
 */
function view(
  string $templateUrl,
  array $data = [],
  ViewProperties|array $props = [],
  ?string $component = null
): View
{
  return new View($templateUrl, $data, $props, $component);
}