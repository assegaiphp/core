<?php

namespace Assegai\Core\Util\Collections\Interfaces;

interface ICollectable
{
  public function add(mixed $item): void;

  public function has(mixed $item): bool;

  public function remove(mixed $item): int|false;

  public function size(): int;
}