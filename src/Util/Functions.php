<?php

use Assegai\Core\Components\ComponentFactory;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Enumerations\EventChannel;
use Assegai\Core\Events\Event;
use Assegai\Core\Events\EventManager;
use Assegai\Core\Exceptions\Container\ContainerException;
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
  /**
   * Renders a component
   *
   * @param class-string<ComponentInterface> $componentClass The name of the component class
   * @throws ReflectionException
   * @throws ContainerException
   */
  function render(string $componentClass): ComponentInterface
  {
    return ComponentFactory::createComponent($componentClass);
  }
}

if (! function_exists('view')) {
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
}

if (! function_exists('broadcast') ) {
  /**
   * Broadcasts an event to the given channel.
   *
   * @param EventChannel $channel The channel to broadcast the event to.
   * @param Event $event The event to broadcast.
   * @return void
   */
  function broadcast(EventChannel $channel, Event $event): void
  {
    EventManager::broadcast($channel, $event);
  }
}