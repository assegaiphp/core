<?php

namespace Tests\Unit;

use Assegai\Core\Util\TypeManager;
use DateTime;
use stdClass;
use Tests\Support\UnitTester;

class TypeManagerCest
{
  public function testNestedStdClassValuesCanHydrateNullableArrayProperties(UnitTester $I): void
  {
    $payload = json_decode(json_encode([
      'name' => 'Default',
      'bodyTemplate' => [
        'data' => [
          [
            'id' => 'item-1',
            'meta' => [
              'flags' => [
                ['name' => 'featured', 'enabled' => true],
              ],
            ],
          ],
        ],
        'summary' => [
          'count' => 1,
        ],
      ],
    ]));

    $dto = TypeManager::castObjectToUserType($payload, TypeManagerArrayBodyDto::class);

    $I->assertInstanceOf(TypeManagerArrayBodyDto::class, $dto);
    $I->assertSame('Default', $dto->name);
    $I->assertIsArray($dto->bodyTemplate);
    $I->assertSame('item-1', $dto->bodyTemplate['data'][0]['id']);
    $I->assertIsArray($dto->bodyTemplate['data'][0]['meta']);
    $I->assertIsArray($dto->bodyTemplate['data'][0]['meta']['flags'][0]);
    $I->assertTrue($dto->bodyTemplate['data'][0]['meta']['flags'][0]['enabled']);
    $I->assertSame(1, $dto->bodyTemplate['summary']['count']);
  }

  public function testTopLevelObjectsCanBeCastToArrays(UnitTester $I): void
  {
    $payload = json_decode(json_encode([
      'items' => [
        ['id' => 'item-1'],
      ],
    ]));

    $result = TypeManager::castObjectToUserType($payload, 'array');

    $I->assertIsArray($result);
    $I->assertSame('item-1', $result['items'][0]['id']);
  }

  public function testNestedDtoPropertiesCanBeHydratedFromStdClassValues(UnitTester $I): void
  {
    $payload = json_decode(json_encode([
      'child' => [
        'name' => 'Nested',
      ],
    ]));

    $dto = TypeManager::castObjectToUserType($payload, TypeManagerParentDto::class);

    $I->assertInstanceOf(TypeManagerParentDto::class, $dto);
    $I->assertInstanceOf(TypeManagerChildDto::class, $dto->child);
    $I->assertSame('Nested', $dto->child->name);
  }

  public function testNullableDatePropertiesPreserveNull(UnitTester $I): void
  {
    $payload = new stdClass();
    $payload->startsAt = null;

    $dto = TypeManager::castObjectToUserType($payload, TypeManagerNullableDateDto::class);

    $I->assertNull($dto->startsAt);
  }

  public function testUnionArrayPropertiesCanAcceptDecodedJsonObjects(UnitTester $I): void
  {
    $payload = json_decode(json_encode([
      'bodyTemplate' => [
        'status' => 'ok',
      ],
    ]));

    $dto = TypeManager::castObjectToUserType($payload, TypeManagerUnionArrayBodyDto::class);

    $I->assertIsArray($dto->bodyTemplate);
    $I->assertSame('ok', $dto->bodyTemplate['status']);
  }

  public function testScalarPropertiesRejectDecodedJsonObjects(UnitTester $I): void
  {
    $payload = json_decode(json_encode([
      'name' => [
        'nested' => true,
      ],
    ]));

    $I->expectThrowable(
      \Assegai\Core\Exceptions\Http\HttpException::class,
      fn() => TypeManager::castObjectToUserType($payload, TypeManagerArrayBodyDto::class),
    );
  }
}

class TypeManagerArrayBodyDto
{
  public string $name = '';
  public ?array $bodyTemplate = null;
}

class TypeManagerParentDto
{
  public ?TypeManagerChildDto $child = null;
}

class TypeManagerChildDto
{
  public string $name = '';
}

class TypeManagerNullableDateDto
{
  public ?DateTime $startsAt = null;
}

class TypeManagerUnionArrayBodyDto
{
  public string|array $bodyTemplate = '';
}