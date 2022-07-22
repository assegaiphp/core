<?php

namespace Assegai\Core\Interfaces;

use Psr\Container\NotFoundExceptionInterface;

/**
 * No entry was found in the container.
 */
interface IEntryNotFoundException extends IContainerException, NotFoundExceptionInterface
{
}