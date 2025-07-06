<?php

namespace Assegai\Core\Queues\Interfaces;

interface QueueProcessorInterface
{
  public function process(mixed $job): void;
}