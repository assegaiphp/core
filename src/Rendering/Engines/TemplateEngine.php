<?php

namespace Assegai\Core\Rendering\Engines;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Rendering\Interfaces\TemplateEngineInterface;

/**
 * The base class for all template engines. This class provides a common interface for all template engines.
 *
 * @package Assegai\Core\Rendering\Engines
 */
abstract class TemplateEngine implements TemplateEngineInterface
{
  /**
   * @var string The template to render.
   */
  protected string $template = '';
  /**
   * @var array The data to render.
   */
  protected array $data = [];
  /**
   * @var ContentType The content type.
   */
  protected ContentType $contentType;
  /**
   * @var ComponentInterface|null $rootComponent  The root component.
   */
  protected ?ComponentInterface $rootComponent = null;
  /**
   * @var Component|null The component attribute instance.
   */
  protected ?Component $rootComponentAttributeInstance = null;
  /**
   * @var string $title The document title.
   */
  protected string $title = '';
  /**
   * @var string $description The document description.
   */
  protected string $description = '';
  /**
   * @var string $keywords The document keywords.
   */
  protected string $keywords = '';
  /**
   * @var string $author The document author.
   */
  protected string $author = '';
  /**
   * @var string $style The style. Add your custom styles here.
   */
  protected string $style = '';
  /**
   * @var array $meta The meta. Add your custom meta tags here.
   */
  protected array $meta = [];
  /**
   * @var array $links The links. Add your custom links here.
   */
  protected array $links = [];
  /**
   * @var array $scripts The scripts. Add your custom scripts here.
   */
  protected array $scripts = [];
  /**
   * @var string|null $base The base URL.
   */
  protected ?string $base = null;

  /**
   * Constructs a TemplateEngine.
   */
  public function __construct()
  {
    $this->contentType = ContentType::HTML;
  }

  /**
   * @inheritDoc
   */
  public function setTemplate(string $template): TemplateEngineInterface
  {
    $this->template = $template;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function setData(array $data): TemplateEngineInterface
  {
    $this->data = $data;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function setContentType(ContentType $contentType): TemplateEngineInterface
  {
    $this->contentType = $contentType;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function setRootComponent(ComponentInterface $rootComponent): self
  {
    $this->rootComponent = $rootComponent;
    $this->rootComponentAttributeInstance = $rootComponent->getAttribute();

    return $this;
  }
}