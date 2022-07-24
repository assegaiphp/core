<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * Binds the current client `Request` to the target parameter.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Req
{
}