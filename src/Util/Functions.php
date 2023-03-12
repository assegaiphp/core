<?php

function json_is_valid(string $input): bool
{
  json_decode($input);

  return json_last_error() === JSON_ERROR_NONE;
}