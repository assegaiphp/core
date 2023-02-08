<?php

namespace Tests\Mocks;

use Assegai\Validation\Attributes\IsString;
use stdClass;

class InvalidMockDto extends stdClass
{
  #[IsString]
  public int $notAString = 0;
}