<?php


namespace Tests\Unit;

use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Pipes\ValidationPipe;
use Exception;
use ReflectionException;
use Tests\Mocks\InvalidMockDto;
use Tests\Mocks\ValidMockDto;
use Tests\Support\UnitTester;

class ValidationPipeCest
{
  public function _before(UnitTester $I): void
  {
    spl_autoload_register(function($className) {
      $className = str_replace('Tests\\Mocks\\', 'Mocks' . DIRECTORY_SEPARATOR, $className);
      include_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "$className.php";
    });
  }

  // tests
  /**
   * @throws ReflectionException
   * @throws HttpException
   */
  public function checkTheTransformMethod(UnitTester $I): void
  {
    $pipe = new ValidationPipe();

    $I->assertEquals(1, $pipe->transform(1));

    $validMockDto = new ValidMockDto();
    $validMockDto->email = 'hello@example.com';
    $I->assertEquals($validMockDto, $pipe->transform($validMockDto));

    $invalidMockDto = new InvalidMockDto();
    try
    {
      $pipe->transform($invalidMockDto);
    }
    catch (Exception $exception)
    {
      $I->assertInstanceOf(HttpException::class, $exception);
      $I->assertJson($exception->getMessage());

      $exceptionMessage = json_decode($exception->getMessage());
      $I->assertEquals($exceptionMessage->statusCode, 500);
      $I->assertEquals($exceptionMessage->message, "Value passed to Validation pipe is not a valid class");
    }
  }
}
