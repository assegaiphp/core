<?php

namespace Assegai\Core\Enumerations;

/**
 * Enumerates the different types of views that Assegai supports.
 */
enum ViewType
{
  case HTML;
  case IMAGE;
  case VIDEO;
  case AUDIO;
  case DOCUMENT;
}