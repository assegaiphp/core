<?php

namespace Assegai\Core\Rendering;

/**
 *
 */
final class ViewEngine
{
  /**
   * @var $this |null
   */
  private static ?self $instance = null;

  /**
   *
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
    $props = $view->props;
    extract($props);

    require $view->templateUrl;
    exit;
  }
}