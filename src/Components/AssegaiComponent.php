<?php

namespace Assegai\Core\Components;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Exceptions\FileException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Exceptions\RenderingException;
use Assegai\Core\ModuleManager;
use Assegai\Core\Util\Paths;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class ViewComponent. This class is for the view component.
 *
 * @package Assegai\Core\Rendering
 */
abstract class AssegaiComponent implements ComponentInterface
{
  /**
   * @var Component The component attribute.
   */
  protected Component $componentAttribute;
  /**
   * @var ModuleManager $moduleManager The module manager.
   */
  protected ModuleManager $moduleManager;
  /**
   * @var LoggerInterface $logger The logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs an AssegaiComponent object.
   *
   * @throws RenderingException
   */
  public final function __construct()
  {
    if (method_exists($this, 'onInit')) {
      $this->onInit();
    }

    $this->componentAttribute = $this->getAttribute();
    $this->moduleManager = ModuleManager::getInstance();
    # Process

    if (method_exists($this, 'afterInit')) {
      $this->afterInit();
    }

    $this->logger = new ConsoleLogger(new ConsoleOutput());
  }

  /**
   * Destructs a AssegaiComponent object.
   */
  public final function __destruct()
  {
    if (method_exists($this, 'onDestroy')) {
      $this->onDestroy();
    }

    # Process

    if (method_exists($this, 'afterDestroy')) {
      $this->afterDestroy();
    }
  }

  /**
   * Returns the content of the template file identified by the given selector.
   *
   * @param string $selector The selector for the template file.
   * @return string The content of the template file.
   * @throws FileException If the file cannot be opened.
   * @throws NotFoundException If the template file does not exist.
   */
  public static function getTemplateContentBySelector(string $selector): string
  {
    $moduleManager = ModuleManager::getInstance();
    $selectedAttribute = $moduleManager->getDeclaredAttributes()[$selector];
    $selectedReflection = $moduleManager->getDeclaredReflections()[$selector];

    if (!$selectedAttribute || !$selectedReflection) {
      die(new RenderingException("Invalid selector"));
    }

    if (!$selectedAttribute->templateUrl) {
      return $selectedAttribute->template;
    }

    $componentPath = Paths::join($selectedReflection->getFileName(), $selectedAttribute->templateUrl);

    if (!file_exists($componentPath)) {
      throw new NotFoundException($componentPath);
    }

    $content = file_get_contents($componentPath);

    if (!$content) {
      throw new FileException("Failed to open file {$content}");
    }

    return $content;
  }

  /**
   * Returns the path to the template file identified by the given selector.
   *
   * @param string $selector The selector for the template file.
   * @return string The path to the template file, or false if an error occurs.
   * @throws RenderingException If the given selector is invalid.
   * @throws NotFoundException If the template file does not exist.
   */
  public static function getTemplatePathBySelector(string $selector): string
  {
    $moduleManager = ModuleManager::getInstance();
    $selectedAttribute = $moduleManager->getDeclaredAttributes()[$selector];
    $selectedReflection = $moduleManager->getDeclaredReflections()[$selector];

    if (!$selectedAttribute || !$selectedReflection) {
      throw new RenderingException("Invalid selector");
    }

    $path = Paths::join($selectedReflection->getFileName(), $selectedAttribute->templateUrl);

    if (!file_exists($path)) {
      throw new NotFoundException($path);
    }

    return $path;
  }

  /**
   * @inheritDoc
   */
  public final function __toString(): string
  {
    if (method_exists($this, 'render')) {
      return $this->render() ?? '';
    }

    $this->logger->warning('No render method found in ' . get_class($this));
    return '';
  }

  /**
   * @inheritDoc
   */
  public function getAttribute(): Component
  {
    $reflection = new ReflectionClass($this);
    $componentAttributes = $reflection->getAttributes(Component::class);

    if (!$componentAttributes) {
      throw new RenderingException("Invalid ViewComponent - Component Attribute not found");
    }

    return $componentAttributes[0]->newInstance();
  }
}