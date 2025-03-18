<?php

use Assegai\Core\App;
use Assegai\Core\Components\ComponentFactory;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Enumerations\EventChannel;
use Assegai\Core\Events\Event;
use Assegai\Core\Events\EventManager;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Rendering\View;
use Assegai\Core\Rendering\ViewProperties;
use Assegai\Core\Util\Paths;

if (!function_exists('env')) {
  function env($key, $default = null): mixed
  {
    return $_ENV[$key] ?? $default;
  }
}

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
  function render(string $componentClass, array $data = []): ComponentInterface
  {
    return ComponentFactory::createComponent($componentClass, $data);
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

if (! function_exists('config') ) {
  function config(string $path, mixed $default = null, ?string $dirname = null): mixed
  {
    $configDirname = $dirname ?? Paths::getConfigDirectory();
    [$directory, $variablePath] = str_split_by_last_needle($path);

    if (!$variablePath) {
      return $default;
    }

    $pathParts = explode('.', $variablePath) ?? [''];

    $filepath = Paths::join($configDirname, $directory, ($pathParts[0] ?? '') . '.php');

    if (! file_exists($filepath) ) {
      return $default;
    }

    $config = require $filepath;
    $firstPeriodIndex = strpos($variablePath, '.');
    $trueVariablePath = substr($variablePath, $firstPeriodIndex + 1);
    $pathParts = explode('.', $trueVariablePath);

    foreach ($pathParts as $key) {
      if (! key_exists($key, $config)) {
        return $default;
      }

      $config = $config[$key];
    }

    return $config;
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

if (! function_exists('translate') ) {
  function translate(string $id, array $parameters = [], string $domain = '', ?string $locale = null): string
  {
    $default = $id;
    $request = Request::getInstance();

    $effectiveLocale = $locale ?? $request->getLang() ?? App::DEFAULT_LOCALE;
    $langFilename = Paths::join(Paths::getLangDirectory(), $domain, $effectiveLocale);
    [$dirname, $path] = str_split_by_last_needle($id);
    $dirname = Paths::join($langFilename, $dirname);

    $translation = config($path, $id, $dirname);

    if (is_array($translation)) {
      return $default;
    }

    foreach ($parameters as $key => $value) {
      $ucFirstKey = ucfirst($key);
      $uppercaseKey = strtoupper($key);

      $translation = str_replace(":$key", $value, $translation);
      $translation = str_replace(":$ucFirstKey", ucfirst($value), $translation);
      $translation = str_replace(":$uppercaseKey", strtoupper($value), $translation);
    }

    return $translation;
  }
}

if (! function_exists('str_split_by_last_needle') ) {
  /**
   * Splits a string by the last occurrence of a needle.
   *
   * @param string $input The string to split.
   * @return array The string split into two parts.
   */
  function str_split_by_last_needle(string $input, string $needle = '/'): array {
    $lastSlashPos = strrpos($input, $needle);

    if ($lastSlashPos === false) {
      return ['', $input]; // No slash found, return empty prefix
    }

    return [
      substr($input, 0, $lastSlashPos),
      substr($input, $lastSlashPos + 1)
    ];
  }
}