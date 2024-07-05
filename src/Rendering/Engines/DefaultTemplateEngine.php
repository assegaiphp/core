<?php

namespace Assegai\Core\Rendering\Engines;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Util\Path;
use ReflectionClass;
use ReflectionException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

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

  public function __construct()
  {
    parent::__construct();
    $this->templatesDirectory = Path::join(getcwd() ?: throw new \RuntimeException('No current working directory'), 'src');
    $this->loader = new FilesystemLoader($this->templatesDirectory);
    $this->twig = new Environment($this->loader);
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

    return <<<START
<!DOCTYPE html>
<html lang="$lang">
  <head>
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

    if ($componentAttributeInstance->styleUrls) {
      $styles = '<style>';
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
}