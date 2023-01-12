<?php /** @noinspection HtmlRequiredTitleElement */

namespace Assegai\Core\Rendering;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\ModuleManager;
use Assegai\Core\Util\Debug\Log;

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
   * @var View|null
   */
  private ?View $view = null;

  private readonly ModuleManager $moduleManager;

  private array $declarations = [
    'app-navbar' => 'Assegai\\App\\Navbar'
  ];

  /**
   * Constructs a ViewEngine object.
   */
  private final function __construct()
  {
    $this->moduleManager = ModuleManager::getInstance();
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
   * @return $this
   */
  public function load(View $view): self
  {
    $this->view = $view;
    return $this;
  }

  /**
   * @return never
   */
  public function render(): never
  {
    header("Content-Type: text/html");

    if (!$this->view)
    {
      die(new RenderingException("Invalid view"));
    }
    $lang = $this->view->props->lang;

    echo <<<START
<!DOCTYPE html>
<html lang="$lang">
  <head>
    <meta name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {$this->view->props}
  </head>
  <body>\n
START;

    $data = $this->view->data;
    extract($data);
    $__render = function(string $selector, array $props = []): string {
      return "Rendering $selector";
    };

    $template = file_get_contents($this->view->templateUrl);

    if (!$template)
    {
      die(new RenderingException("Invalid template"));
    }

    if ($component = $this->view->getComponent())
    {
      echo $this->resolveTemplates(template: $template, component: $component);
    }
    else
    {
      require $this->view->templateUrl;
    }

    echo PHP_EOL;
    echo $this->view->props->generateBodyScriptTags();
    echo $this->view->props->generateBodyScriptImportTags();

    echo <<<END
  </body>
</html>
END;
    exit;
  }

  /**
   * @param string $template
   * @param Component $component
   * @return string
   */
  private function resolveTemplates(string $template, Component $component): string
  {
    $html = $template;
    $declaredSelectors = $this->getDeclaredSelectors();
    $selectionPattern = "/(" . implode('|', $declaredSelectors) . ")/";
    $inputLines = explode("\n", $html);

    if (preg_match_all($selectionPattern, $html, $matches))
    {
      foreach ($matches as $tokens)
      {
        $tokens = array_unique($tokens);

        foreach ($tokens as $token)
        {
          $message = ViewComponent::getTemplateContentBySelector($token);
//          $templatePath = ViewComponent::getTemplatePathBySelector($token);
//          $message = "include $templatePath;";

          Log::error(__METHOD__, $message);
          $search = "<$token></$token>";
          $html = str_replace($search, $message, $html);
        }
      }
    }

    return $html;
  }

  /**
   * @return string[]
   */
  private function getDeclaredSelectors(): array
  {
    $attributes = $this->moduleManager->getDeclaredAttributes();
    return array_map(fn(Component $attribute) => $attribute->selector, $attributes);
  }
}