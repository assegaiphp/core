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

  public function testTheMimeTypeResolverUsesBrowserSafeTypesForCommonAssets(UnitTester $I): void
  {
    $directory = sys_get_temp_dir() . '/assegai-paths-' . uniqid('', true);

    if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
      $I->fail("Failed to create temporary directory: $directory");
    }

    $css = $directory . '/style.css';
    $js = $directory . '/main.js';
    $webp = $directory . '/logo.webp';

    try {
      file_put_contents($css, 'body {}');
      file_put_contents($js, '');
      file_put_contents($webp, 'webp-bytes');

      $I->assertSame('text/css', Paths::getMimeType($css));
      $I->assertSame('text/javascript', Paths::getMimeType($js));
      $I->assertSame('image/webp', Paths::getMimeType($webp));
    } finally {
      foreach ([$css, $js, $webp] as $filename) {
        if (is_file($filename)) {
          unlink($filename);
        }
      }

      if (is_dir($directory)) {
        rmdir($directory);
      }
    }
  }
}
