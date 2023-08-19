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