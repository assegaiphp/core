<?php

namespace Assegai\Core\Files;

use Assegai\Core\Attributes\Injectable;
use JetBrains\PhpStorm\ArrayShape;

/**
 *
 */
#[Injectable]
class TempFile
{
  /**
   * @var string
   */
  protected string $targetDirectory = DEFAULT_STORAGE_PATH;
  /**
   * @var string
   */
  protected string $targetFilename;

  /**
   * @param string|null $name
   * @param string|null $fullPath
   * @param string|null $type
   * @param string|null $tmpName
   * @param int|null $error
   * @param int|null $size
   * @param string|null $extension
   */
  public function __construct(
    public ?string $name = null,
    public ?string $fullPath = null,
    public ?string $type = null,
    public ?string $tmpName = null,
    public ?int $error = 0,
    public ?int $size = 0,
    protected ?string $extension = null
  )
  {
    $this->targetFilename = $this->targetDirectory . "/$this->name";
    if (! $this->extension)
    {
      $this->extension = strtolower(pathinfo($this->targetFilename, PATHINFO_EXTENSION));
    }
  }

  /**
   * @return string
   */
  public function getTargetFilename(): string
  {
    return $this->targetFilename;
  }

  /**
   * @param string $targetFilename
   */
  public function setTargetFilename(string $targetFilename): void
  {
    $this->targetFilename = $targetFilename;
  }

  /**
   * @return array
   */
  #[ArrayShape(['name' => "string", 'fullPath' => "string", 'type' => "null|string", 'tmpName' => "string", 'error' => "int", 'size' => "int"])]
  public function toArray(): array
  {
    return [
      'name' => $this->name,
      'fullPath' => $this->fullPath,
      'type' => $this->type,
      'tmpName' => $this->tmpName,
      'error' => $this->error,
      'size' => $this->size,
    ];
  }

  /**
   * @param array $array
   * @return static
   */
  public static function fromArray(array $array): self
  {
    return new self(
      name: $array['name'] ?? '',
      fullPath: $array['fullPath'] ?? '',
      type: $array['type'] ?? '',
      tmpName: $array['tmpName'] ?? '',
      error: $array['error'] ?? 0,
      size: $array['size'] ?? 0,
    );
  }

  /**
   * @return string
   */
  public function toJSON(): string
  {
    return json_encode($this->toArray());
  }

  /**
   * @return string
   */
  public function __toString(): string
  {
    return $this->toJSON();
  }
}