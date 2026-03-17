<?php

namespace Unit;

use Assegai\Core\Util\Paths;
use Tests\Support\UnitTester;

class PathsCest
{
  public function testThePublicPathResolverStripsQueryStrings(UnitTester $I): void
  {
    $workingDirectory = getcwd();

    $I->assertNotFalse($workingDirectory);

    $expected = Paths::join($workingDirectory ?: '', 'public', '/.assegai/wc-hot-reload.json');

    $I->assertSame(
      $expected,
      Paths::getPublicPath('/.assegai/wc-hot-reload.json?t=1742078500')
    );
  }
}
