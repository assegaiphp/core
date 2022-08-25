<?php

namespace Assegai\Core\Attributes;

use Attribute;

/**
 * An attribute that binds the current system `Response` object to the target parameter.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Res
{
}