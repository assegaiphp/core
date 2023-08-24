<?php

namespace Tests\Unit;

use Assegai\Core\Http\Requests\RequestQuery;
use Tests\Support\UnitTester;

class RequestQueryCest
{
  protected ?RequestQuery $query = null;

  public function _before(UnitTester $I)
  {
    $_SERVER['QUERY_STRING'] = 'foo=bar&baz=qux';
    $this->query = new RequestQuery();
  }

  public function testTheGetMethod(UnitTester $I): void
  {
    // TODO: Implement testTheGetMethod() method.
    $I->assertIsArray($this->query->get());
    $I->assertIsString($this->query->get('foo'));
    $I->assertEquals('bar', $this->query->get('foo'));
    $I->assertEquals('default', $this->query->get('not_found', 'default'));
    $I->assertIsString($this->query->get('not_found'));
  }

  public function testTheHasMethod(UnitTester $I): void
  {
    $I->assertTrue($this->query->has('foo'));
    $I->assertFalse($this->query->has('not_found'));
  }

  public function testTheToArrayMethod(UnitTester $I): void
  {
    $I->assertIsArray($this->query->toArray());
    $I->assertNotEquals(['foo' => 'bar'], $this->query->toArray());
    $I->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $this->query->toArray());
  }
}