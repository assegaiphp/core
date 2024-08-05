<?php

namespace Assegai\Core\Rendering\Engines;

use Assegai\Core\ControllerManager;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Injector;
use Assegai\Core\ModuleManager;
use Assegai\Core\Routing\Router;
use Assegai\Util\Path;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/**
 * The default template engine.
 *
 * @package Assegai\Core\Rendering\Engines
 */
class DefaultTemplateEngine extends TemplateEngine
{
  /**
   * The Twig filesystem loader.
   *
   * @var FilesystemLoader
   */
  protected FilesystemLoader $loader;
  /**
   * The Twig environment.
   *
   * @var Environment
   */
  protected Environment $twig;
  /**
   * The templates directory.
   *
   * @var string
   */
  protected string $templatesDirectory = '';
  /**
   * The component filename.
   *
   * @var string
   */
  protected string $componentFilename = '';

  /**
   * The module manager.
   *
   * @var ModuleManager
   */
  protected ModuleManager $moduleManager;

  /**
   * Constructs a DefaultTemplateEngine.
   *
   * @param array{
   *   root_module_class: class-string|null,
   *   router: Router|null,
   *   module_manager: ModuleManager|null,
   *   controller_manager: ControllerManager|null,
   *   injector: Injector|null
   * } $options The options.
   */
  public function __construct(protected array $options = [
    'root_module_class' => null,
    'router' => null,
    'module_manager' => null,
    'controller_manager' => null,
    'injector' => null
  ])
  {
    parent::__construct();
    $this->templatesDirectory = Path::join(getcwd() ?: throw new RuntimeException('No current working directory'), 'src');
    $this->loader = new FilesystemLoader($this->templatesDirectory);
    $this->twig = new Environment($this->loader);
    $this->twig->addExtension(new MarkdownExtension());
    $this->twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
      public function load($class): ?MarkdownRuntime
      {
        if (MarkdownRuntime::class === $class) {
          return new MarkdownRuntime(new DefaultMarkdown());
        }

        return null;
      }
    });
    $this->moduleManager = $this->options['module_manager'] ?? ModuleManager::getInstance();
  }

  /**
   * @inheritDoc
   *
   * @throws LoaderError
   * @throws RenderingException
   * @throws RuntimeError
   * @throws SyntaxError
   * @throws ReflectionException
   */
  public function render(): string
  {
    # Get component attribute instance
    $componentAttributeInstance = $this->rootComponentAttributeInstance;
    $componentReflection = new ReflectionClass($this->rootComponent);
    $this->componentFilename = $componentReflection->getFileName();
    $pathRelativeFromRoot = Path::relative($this->templatesDirectory, $this->componentFilename);
    $resolveTemplateUrl = Path::join(dirname($pathRelativeFromRoot) ?: '', $componentAttributeInstance->templateUrl);
    $this->setTemplate($componentAttributeInstance->template ?? '');

    # Load template
    if (!$componentAttributeInstance->templateUrl) {
      return $this->template;
    }

    $template = $this->twig->load(Path::normalize($resolveTemplateUrl));
    $data = [];
    foreach ($componentReflection->getProperties() as $reflectionProperty) {
      $data[$reflectionProperty->getName()] = $reflectionProperty->getValue($this->rootComponent);
    }

    # Load data from declared components
    foreach ($this->moduleManager->getDeclarations() ?? [] as $key => $declaration) {
      $declarationData = get_object_vars($declaration);
      $data = [...$declarationData, ...$data];
    }

    $this->setData($data);

    # Unwrap view Properties
    $lang ??= 'en';
    $props ??= <<<PROPS
<link href="/css/style.css" rel="stylesheet" />
<script src="/js/main.js"></script>
PROPS;

    # Render template
    $props .= $this->renderStyles();
    $output = $template->render([...$this->data]);
    $charSet = $this->meta['charset'] ?? 'UTF-8';

    return <<<START
<!DOCTYPE html>
<html lang="$lang">
  <head>
    <title>$this->title</title>
    <meta charset="$charSet">
    <meta name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {$props}
  </head>
  <body>\n
    $output
    <script src="https://unpkg.com/htmx.org@1.9.12"></script>
  </body>
</html>
START;
  }

  /**
   * @return string
   * @throws RenderingException
   */
  private function renderStyles(): string
  {
    $styles = '';
    $componentAttributeInstance = $this->rootComponent->getAttribute();

    if ($this->getGlobalStyles()) {
      $styles .= '<style>';
      $styles .= $this->getGlobalStyles();
      $styles .= '</style>';
    }

    if ($componentAttributeInstance->styleUrls) {
      $styles .= '<style>';
      foreach ($componentAttributeInstance->styleUrls as $styleUrl) {
        $stylesheetFilename =
          Path::normalize(
            Path::join(dirname($this->componentFilename), $styleUrl)
          );
        $styles .= file_get_contents($stylesheetFilename) ?: throw new RenderingException('Failed to read stylesheet file.')  ;
      }
      $styles .= '</style>';

      return $styles;
    }

    if ($componentAttributeInstance->styles) {
      $styles = '<style>';
      $styles .= $componentAttributeInstance->styles;
      $styles .= '</style>';
    }
    return $styles;
  }

  /**
   * Returns the resolved template URL.
   *
   * @return string The resolved template URL.
   * @throws ReflectionException
   * @throws RenderingException
   */
  public function getResolvedTemplateURL(): string
  {
    $componentAttributeInstance = $this->rootComponentAttributeInstance ?? throw new RenderingException('Root component attribute instance is not set.');
    $componentReflection = new ReflectionClass($this->rootComponent);
    $componentFilename = $componentReflection->getFileName();
    $pathRelativeFromRoot = Path::relative($this->templatesDirectory, $componentFilename);

    return Path::join(dirname($pathRelativeFromRoot) ?: '', $componentAttributeInstance->templateUrl);
  }

  /**
   * Get the global styles.
   *
   * @return false|string The global styles.
   */
  private function getGlobalStyles(): false|string
  {
    // Get the global styles
    $output = false;

    if ($this->style) {
      $output = $this->style;
    }

    if (! empty($this->moduleManager->getDeclaredStyles()) ) {
      $output .= implode("\n", $this->moduleManager->getDeclaredStyles());
    }

    return preg_replace('/\/\s*\*.*\*\s*\//', '', $output);
  }
}