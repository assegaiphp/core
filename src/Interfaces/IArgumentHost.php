<?php

namespace Assegai\Core\Interfaces;

use Assegai\Core\Enumerations\Http\ContextType;

interface IArgumentHost
{
  /**
   * @return ContextType
   */
  public function getType(): ContextType;

  /**
   * @return array
   */
  public function getArgs(): array;

  /**
   * @return array
   */
  public function getArgsByIndex(int $index): array;
}