<?php

namespace Assegai\Core\Exceptions\Container;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Interfaces\IEntryNotFoundException;
use Throwable;

class EntryNotFoundException extends HttpException implements IEntryNotFoundException
{
  public function __construct(string $entryId, ?Throwable $previous = null)
  {
    parent::__construct("Entry not found: $entryId", previous: $previous);
  }
}
