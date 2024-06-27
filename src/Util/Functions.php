<?php

/**
 * Returns true if the JSON string is valid, false otherwise.
 *
 * @param string $input The JSON string to validate
 * @return bool Returns true if the JSON string is valid, false otherwise.
 */
function json_is_valid(string $input): bool
{
  json_decode($input);

  return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Dump the variables and exit.
 *
 * @param mixed ...$variables The variables to print.
 * @return never
 */
function debug_and_exit(mixed ...$variables): never
{
  foreach ($variables as $index => $variable) {
    printf("\e[0;33m%d\e[0m]\t-\t%s\n", $index, var_export($variable, true));
  }
  exit(1);
}