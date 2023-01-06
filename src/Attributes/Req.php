<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * An attribute that binds the current client `Request` to the target parameter.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Req
{
}