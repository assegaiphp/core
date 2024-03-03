<?php

namespace Assegai\Core\Enumerations;

/**
 * Enumerates the different types of environments that Assegai supports.
 */
enum EnvironmentType: string
{
  case LOCAL = 'LOCAL';
  case DEVELOP = 'DEV';
  case QA = 'QA';
  case STAGING = 'STAGING';
  case PRODUCTION = 'PROD';
  case TEST = 'TEST';
}