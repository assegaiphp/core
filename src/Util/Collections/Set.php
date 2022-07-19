<?php

namespace Assegai\Core\Util\Collections;

use Assegai\Core\Util\Collections\Interfaces\ICollectable;

class Set implements ICollectable
{
  protected array $array = [];

  public function __construct(object|array $items = [])
  {
    foreach ($items as $item)
    {
      $this->add($item);
    }
  }

  public function add(mixed $item): void
  {
    if (!$this->has($item))
    {
      $this->array[] = $item;
    }
  }

  public function has(mixed $item): bool
  {
    return in_array($item, $this->array);
  }

  public function remove(mixed $item): int|false
  {
    $index = array_search($item, $this->array);

    if ($index === false)
    {
      return false;
    }

    unset($this->array[$index]);

    return $this->size();
  }

  public function size(): int
  {
    return count($this->array);
  }
}