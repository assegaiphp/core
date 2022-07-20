<?php

namespace Assegai\Core\Exceptions\Container;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Interfaces\IEntryNotFoundException;

class EntryNotFoundException extends HttpException implements IEntryNotFoundException
{
  public function __construct(string $entryId)
  {
    parent::__construct("Entry not found: $entryId");
  }
}