<?php

namespace Assegai\Core\Rendering;

/**
 *
 */
final class ViewEngine
{
  /**
   * @var ViewEngine|null $this |null
   */
  private static ?self $instance = null;

  /**
   * Constructs a ViewEngine object.
   */
  private final function __construct()
  {
  }

  /**
   * @return static
   */
  public static function getInstance(): self
  {
    if (! self::$instance )
    {
      self::$instance = new ViewEngine();
    }

    return self::$instance;
  }

  /**
   * @param View $view
   * @return never
   */
  public function render(View $view): never
  {
    header("Content-Type: text/html");

    $lang = $view->props->lang;

    echo <<<START
<!DOCTYPE html>
<html lang="$lang">
  <head>
    $view->props
  </head>
  <body>\n
START;

    $data = $view->data;
    extract($data);
    $__render = function(string $selector, array $props = []): string {
      return "Rendering $selector";
    };

      require $view->templateUrl;
    echo PHP_EOL;
    echo $view->props->generateBodyScriptTags();
    echo $view->props->generateBodyScriptImportTags();

    echo <<<END
  </body>
</html>
END;
    exit;
  }
}