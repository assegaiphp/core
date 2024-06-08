<?php

namespace Unit;

use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Interfaces\AppInterface;
use Tests\Mocks\MockApp;
use Tests\Support\UnitTester;

class RequestCest
{
  protected ?Request $request = null;

  public function _before(): void
  {
    $_SERVER['REQUEST_URI'] = '/test';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_HOST'] = 'localhost';

    $this->request = Request::getInstance();
  }

  public function _after(): void
  {
  }

  public function testTheGetInstanceMethod(UnitTester $I): void
  {
    $I->assertInstanceOf(Request::class, $this->request);
  }

  public function testTheAppGetterAndSetter(UnitTester $I): void
  {
    $app = new MockApp();

    $I->assertNull($this->request->getApp());
    $this->request->setApp($app);
    $I->assertInstanceOf(AppInterface::class, $this->request->getApp());
  }
}