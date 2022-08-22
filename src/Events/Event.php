<?php

namespace Assegai\Core\Events;

class Event
{
  public function __construct(public readonly mixed $payload)
  {}
}