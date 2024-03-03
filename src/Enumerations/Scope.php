<?php

namespace Assegai\Core\Enumerations;

/**
 * Enumerates the different types of scopes that Assegai supports.
 */
enum Scope
{
  case DEFAULT;
  case TRANSIENT;
  case REQUEST;
}