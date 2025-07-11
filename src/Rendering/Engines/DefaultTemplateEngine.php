<?php

namespace Assegai\Core\Rendering\Engines;

use Assegai\Core\ControllerManager;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Injector;
use Assegai\Core\ModuleManager;
use Assegai\Core\Routing\Router;
use Assegai\Util\Path;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
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
   * @return string
   * @throws LoaderError
   * @throws ReflectionException
   * @throws RenderingException
   * @throws RuntimeError
   * @throws SyntaxError
   * @throws DateInvalidTimeZoneException
   * @throws DateMalformedStringException
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
    $this->meta = [...$this->meta, ...$componentAttributeInstance->meta];

    # Load template
    if (!$componentAttributeInstance->templateUrl) {
      return $this->template;
    }

    $template = $this->twig->load(Path::normalize($resolveTemplateUrl));
    $data = [];

    $ctx = new class {
      private array $methods = [];

      public function addMethod(string $name, callable $method): void {
        $this->methods[$name] = $method;
      }

      public function __call($name, $arguments) {
        if (isset($this->methods[$name])) {
          return call_user_func_array($this->methods[$name], $arguments);
        }
        throw new HttpException("Method $name does not exist.");
      }
    };

    $methods = $componentReflection->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
      $methodName = $method->getName();

      if (str_starts_with($methodName, '__')) {
        continue;
      }

      if (in_array($methodName, ['afterPropertiesBound', 'render', 'onInit', 'afterInit', 'getTemplatePathBySelector', 'getAttribute', 'getTemplateContentBySelector'])) {
        continue;
      }

      $ctx->addMethod($methodName, fn(...$args) => $method->invoke($this->rootComponent, ...$args));
    }

    if (!method_exists($ctx, 'config')) {
      $ctx->addMethod('config', fn(string $path, mixed $default = null, ?string $dirname = null) => config($path, $default, $dirname));
    }

    if (!method_exists($ctx, 'translate')) {
      $ctx->addMethod('translate', fn(string $id, array $parameters = [], string $domain = '', ?string $locale = null) => translate($id, $parameters, $domain, $locale));
    }

    if (!method_exists($ctx, 'timeAgo')) {
      $ctx->addMethod('timeAgo', fn(int|string|null $timestamp, ?string $timezone = null) => time_ago($timestamp, $timezone));
    }

    if (!method_exists($ctx, 'env')) {
      $ctx->addMethod('env', fn(string $key, mixed $default = null) => env($key, $default));
    }

    if (!method_exists($ctx, 'getLang')) {
      $ctx->addMethod('getLang', fn() => Request::getInstance()->getLang());
    }

    $data['ctx'] = $ctx;

    foreach ($componentReflection->getProperties() as $reflectionProperty) {
      $data[$reflectionProperty->getName()] = $reflectionProperty->getValue($this->rootComponent);
    }

    # Load data from declared components
    foreach ($this->moduleManager->getDeclarations() ?? [] as $declaration) {
      $declarationData = get_object_vars($declaration);
      $data = [...$declarationData, ...$data];
    }

    $this->setData($data);

    if ($this->meta) {
      extract($this->meta);
    }

    if (!$this->title) {
      $this->title = $title ?? $_ENV['DOCUMENT_TITLE'] ?? config('app.title', 'AssegaiPHP');
    }

    if (!$this->description) {
      $this->description = $description ?? $_ENV['DOCUMENT_DESCRIPTION'] ?? config('app.description', 'AssegaiPHP Application');
    }

    if (!$this->keywords) {
      $this->keywords = $keywords ?? $_ENV['DOCUMENT_KEYWORDS'] ?? config('app.keywords', 'AssegaiPHP, PHP, Framework');
    }

    if (!$this->author) {
      $this->author = $charset ?? $_ENV['DOCUMENT_AUTHOR'] ?? config('app.author', '');
    }

    # Unwrap view Properties
    $lang ??= Request::getInstance()->getLang();
    $props ??= <<<PROPS
<link href="/css/style.css" rel="stylesheet" />
<script src="/js/main.js"></script>
PROPS;

    # Render template
    $props .= $this->loadStyles();
    $props .= $this->loadScripts();
    $output = $template->render([...$this->data]);
    $charSet = $this->meta['charset'] ?? 'UTF-8';

    return <<<START
<!DOCTYPE html>
<html lang="$lang">
  <head>
    <title>$this->title</title>
    <meta charset="$charSet">
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
   * Load any inline or external stylesheets.
   *
   * @return string
   * @throws RenderingException
   */
  private function loadStyles(): string
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
   * Load any inline or external scripts.
   *
   * @return string
   * @throws RenderingException
   */
  private function loadScripts(): string
  {
    $scripts = '';
    $componentAttributeInstance = $this->rootComponent->getAttribute();

    // Load component script urls
    if ($componentAttributeInstance->scriptUrls) {
      foreach ($componentAttributeInstance->scriptUrls as $scriptUrl) {
        $scriptFilename = Path::normalize(Path::join(dirname($this->componentFilename), $scriptUrl));
        $scripts .= '<script>';
        $scripts .= file_get_contents($scriptFilename) ?: throw new RenderingException('Failed to read script file.');
        $scripts .= '</script>';
      }

      return $scripts;
    }

    // Load component scripts
    if ($componentAttributeInstance->scripts) {
      $scripts .= '<script>';
      $scripts .= $componentAttributeInstance->scripts;
      $scripts .= '</script>';
    }

    return $scripts;
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