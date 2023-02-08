<?php

namespace Tests\Mocks;

use Assegai\Validation\Attributes\IsEmail;
use stdClass;

class ValidMockDto extends stdClass
{
  #[IsEmail]
  public string $email = 'hello@example.com';
}