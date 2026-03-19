<?php

namespace Assegai\Core\Rendering;

/**
 * View-specific alias of the shared document properties model.
 *
 * Views can keep referring to `ViewProperties`, while component rendering can
 * use the neutral `DocumentProperties` type directly.
 */
class ViewProperties extends DocumentProperties
{
}
