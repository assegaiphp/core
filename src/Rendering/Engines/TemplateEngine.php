<?php

namespace Assegai\Core\Rendering\Engines;

use Assegai\Core\Attributes\Component;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Rendering\Interfaces\TemplateEngineInterface;

abstract class TemplateEngine implements TemplateEngineInterface
{
  /**
   * The template to render.
   *
   * @var string
   */
  protected string $template = '';
  /**
   * The data to render.
   *
   * @var array
   */
  protected array $data = [];
  /**
   * The content type.
   *
   * @var ContentType
   */
  protected ContentType $contentType;
  /**
   * The root component.
   *
   * @var ComponentInterface|null $rootComponent  The root component.
   */
  protected ?ComponentInterface $rootComponent = null;
  /**
   * The component attribute instance.
   *
   * @var Component|null
   */
  protected ?Component $rootComponentAttributeInstance = null;

  /**
   * The title.
   *
   * @var string $title
   */
  protected string $title = '';

  /**
   * The style. Add your custom styles here.
   *
   * @var string $style
   */
  protected string $style = '';

  /**
   * The meta. Add your custom meta tags here.
   *
   * @var array $meta
   */
  protected array $meta = [];

  /**
   * The links. Add your custom links here.
   *
   * @var array $links
   */
  protected array $links = [];

  /**
   * The scripts. Add your custom scripts here.
   *
   * @var array $scripts
   */
  protected array $scripts = [];

  /**
   * The base URL.
   *
   * @var string|null $base
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