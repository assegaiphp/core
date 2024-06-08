<?php


namespace Unit;

use Assegai\Core\Config;
use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Exceptions\ConfigurationException;
use Assegai\Core\Util\Paths;
use Assegai\Orm\Enumerations\DataSourceType;
use Codeception\Attribute\Skip;
use Exception;
use Tests\Support\UnitTester;

class ConfigCest
{
  private string $workingDirectory = '';
  private string $appName = 'assegai_app';
  private string $databaseName = 'assegai_test_db';
  private string $testKey = '';
  private string $testValue = '';

  /**
   * @param UnitTester $I
   * @return void
   * @throws ConfigurationException
   */
  public function _before(UnitTester $I): void
  {
    $this->workingDirectory = Paths::join(dirname(__DIR__, 2), 'tests', 'Unit', 'src_test');
    if (isset($GLOBALS['config']))
    {
      unset($GLOBALS['config']);
    }

    $I->assertFalse(isset($GLOBALS['config']));
    Config::hydrate($this->workingDirectory);

    $workspaceConfigFilename = Paths::join($this->workingDirectory, 'assegai.json');
    if (! is_file($workspaceConfigFilename))
    {
      // Assume the file does not exist and create it
      $workspaceConfig = json_encode([$this->testKey => $this->testValue], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
      $bytesWritten = file_put_contents($workspaceConfigFilename, $workspaceConfig);

      if (false === $bytesWritten)
      {
        throw new ConfigurationException("Failed to write to configuration file: $workspaceConfigFilename");
      }
    }

    $workspaceConfig = file_get_contents($workspaceConfigFilename);
    if (json_is_valid($workspaceConfig))
    {
      $workspaceConfig = json_decode($workspaceConfig, true);

      if (json_last_error() !== JSON_ERROR_NONE)
      {
        throw new ConfigurationException("Failed to decode the workspace configuration file: $workspaceConfigFilename");
      }

      $testKey = $this->testKey;
      if (isset($workspaceConfig->$testKey))
      {
        unset($workspaceConfig->$testKey);
      }

      $workspaceConfig = json_encode($workspaceConfig, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

      if (json_last_error() !== JSON_ERROR_NONE)
      {
        throw new ConfigurationException("Failed to encode the workspace configuration: $workspaceConfig");
      }

      $bytesWritten = file_put_contents($workspaceConfigFilename, $workspaceConfig);

      if (false === $bytesWritten)
      {
        throw new ConfigurationException("Failed to write to configuration file: $workspaceConfigFilename");
      }
    }

    $this->testKey = 'test_key';
    $this->testValue = 'test_value_' . time();
  }

  // tests
  public function testTheHydrateMethod(UnitTester $I): void
  {
    $I->assertTrue(isset($GLOBALS['config']));
    $I->assertNotEmpty($GLOBALS['config']);
  }

  public function testTheGetMethod(UnitTester $I): void
  {
    $I->assertNotEmpty(Config::get('databases'));
    $I->assertNotEmpty(Config::get('databases')['mysql']);
    $I->assertNotEmpty(Config::get('databases')['mysql'][$this->databaseName]);
    $I->assertEquals($this->appName, Config::get('app_name'));
  }

  public function testTheDatabaseMethod(UnitTester $I): void
  {
    $database = Config::database(DataSourceType::MYSQL->value, $this->databaseName);
    $I->assertNotEmpty($database);

    $invalidDatabaseType = 'fakesql';
    $nonExistentDatabase = Config::database($invalidDatabaseType, $this->databaseName);
    $I->assertEmpty($nonExistentDatabase);
  }

  public function testTheSetMethod(UnitTester $I): void
  {
    $databaseType = DataSourceType::POSTGRESQL->value;
    $databaseName = 'new_assegai_test_db';
    $newDatabase = [
      'host' => 'localhost',
      'user' => 'Gawa',
      'pass' => 'Undi',
      'port' => 5432
    ];

    Config::set("databases", [ $databaseType => [ $databaseName => $newDatabase] ]);
    Config::hydrate($this->workingDirectory);

    $I->assertArrayHasKey($databaseType, $GLOBALS['config']['databases']);
    $I->assertArrayHasKey($databaseName, $GLOBALS['config']['databases'][$databaseType]);
    $I->assertEquals($newDatabase, $GLOBALS['config']['databases'][$databaseType][$databaseName]);
  }

  /** @noinspection SpellCheckingInspection */
  public function testTheGetasobjectMethod(UnitTester $I): void
  {
    $I->assertIsObject(Config::getAsObject('databases'));
  }

  public function testTheEnvironmentMethod(UnitTester $I): void
  {
    $isDevelopmentEnvironment = Config::environment() === EnvironmentType::DEVELOP;
    $isProductionEnvironment = Config::environment() === EnvironmentType::PRODUCTION;

    $I->assertTrue($isDevelopmentEnvironment);
    $I->assertFalse($isProductionEnvironment);
  }

  #[Skip]
  public function testTheSetEnvironmentMethod(UnitTester $I): void
  {
    $testKey = 'test_key';
    $testValue = 'test_value';
    Config::setEnvironment($testKey, $testValue);

    $I->assertArrayHasKey($testKey, $_ENV);
    $I->assertEquals($testValue, $_ENV[$testKey]);
  }

  /** @noinspection SpellCheckingInspection */
  /**
   * @param UnitTester $I
   * @return void
   * @throws Exception
   */
  public function testTheWorkspaceMethods(UnitTester $I): void
  {
    Config::updateWorkspaceConfig($this->testKey, $this->testValue, $this->workingDirectory);

    $workspaceValue = Config::getWorkspaceConfig($this->testKey, $this->workingDirectory);
    $I->assertEquals($this->testValue, $workspaceValue);
  }

  /** @noinspection SpellCheckingInspection */
  public function testTheIsdebugMethods(UnitTester $I): void
  {
    $I->assertFalse(Config::isDebug());
  }
}
