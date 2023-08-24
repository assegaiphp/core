<?php

namespace Tests\Unit;

use Assegai\Core\Http\Requests\RequestQuery;
use Tests\Support\UnitTester;

class RequestQueryCest
{
  protected ?RequestQuery $query = null;

  public function _before(UnitTester $I)
  {
    $this->query = new RequestQuery();
  }

  public function testQueryParams(UnitTester $I): void
  {
    $I->assertTrue(true);
  }
}