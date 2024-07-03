<?php

namespace Assegai\Core\Rendering\Engines;

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
  protected FilesystemLoader $loader;
  protected Environment $twig;

  public function __construct()
  {
    parent::__construct();
    $templatesDirectory = Path::join(getcwd() ?: throw new \RuntimeException('No current working directory'), 'src');
    $this->loader = new FilesystemLoader($templatesDirectory);
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
    $componentAttributeInstance = $this->rootComponent->getAttribute();
    $this->setTemplate($componentAttributeInstance->templateUrl);

    # Load template
    if (!$componentAttributeInstance->templateUrl) {
      return $this->template;
    }

    $template = $this->twig->load($componentAttributeInstance->templateUrl);
    $componentReflection = new ReflectionClass($this->rootComponent);
    $data = [];
    foreach ($componentReflection->getProperties() as $reflectionProperty) {
      $data[$reflectionProperty->getName()] = $reflectionProperty->getValue($this->rootComponent);
    }
    $this->setData($data);

    # Render template
    return $template->render([...$this->data]);
  }
}