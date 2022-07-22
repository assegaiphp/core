<?php

namespace Assegai\Core;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Attributes\Module;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Util\Debug\Log;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;

class ModuleManager
{
  protected static ?ModuleManager $instance = null;

  /** @var ReflectionAttribute[] $lastLoadedAttributes */
  protected array $lastLoadedAttributes = [];

  /**
   * @var ReflectionAttribute[] A list of all the imported module tokens
   */
  protected array $moduleTokensList = [];

  /**
   * @var array A list of all the imported module tokens
   */
  protected array $controllerTokensList = [];

  /**
   * @var array A list of all the imported module tokens
   */
  protected array $providerTokensList = [];

  private final function __construct()
  {
  }

  /**
   * @return ModuleManager
   */
  public static function getInstance(): ModuleManager
  {
    if (empty(self::$instance))
    {
      self::$instance = new ModuleManager();
    }

    return self::$instance;
  }

  /**
   * @throws \Assegai\Core\Exceptions\Http\HttpException
   */
  public function buildModuleTokensList(string $rootToken): array
  {
    try {
      $reflectionModule = new ReflectionClass($rootToken);
      if (!$this->isValidModule($reflectionModule))
      {
        Log::error(__CLASS__, "Invalid Token ID: $rootToken");
        throw new HttpException();
      }
      $reflectionModuleAttribute = $this->lastLoadedAttributes[0];

      /** @var ['imports' => 'array', 'exports' => 'array', 'providers' => 'array', 'controllers' => 'array'] $args */
      $args = $reflectionModuleAttribute->getArguments();

      # 1. Add rootToken to the list
      $this->moduleTokensList[$rootToken] = $reflectionModuleAttribute;

      # 2. For each import
      foreach ($args['imports'] as $import)
      {
        $this->buildModuleTokensList($import);
      }
    }
    catch (ReflectionException $e)
    {
      throw new HttpException($e->getMessage());
    }

    return $this->getModuleTokens();
  }

  /**
   * @return ReflectionAttribute[]
   */
  public function getModuleTokens(): array
  {
    return $this->moduleTokensList;
  }

  /**
   * @return array
   */
  public function getProviderTokensList(): array
  {
    return $this->providerTokensList;
  }

  /**
   * @param ReflectionClass $reflectionClass
   * @return bool
   */
  private function isValidModule(ReflectionClass $reflectionClass): bool
  {
    $this->lastLoadedAttributes = $reflectionClass->getAttributes(Module::class);

    return !empty($this->lastLoadedAttributes);
  }

  /**
   * @return string[] Returns a list of Provider tokenIds
   * @throws EntryNotFoundException
   */
  public function buildProviderTokensList(): array
  {
    foreach ($this->moduleTokensList as $module)
    {
      /** @var ['imports' => 'array', 'exports' => 'array', 'providers' => 'array'] $args */
      $args = $module->getArguments();

      foreach ($args['providers'] as $tokenId)
      {
        if ($provider = $this->validateProvider($tokenId))
        {
          $this->providerTokensList[$tokenId] = $provider;
        }
      }
    }

    return $this->getProviderTokensList();
  }

  /**
   * @param string $tokenId
   * @return ReflectionAttribute|null
   * @throws EntryNotFoundException
   */
  private function validateProvider(string $tokenId): ?ReflectionClass
  {
    try
    {
      $reflectionClass = new ReflectionClass($tokenId);
      $this->lastLoadedAttributes = $reflectionClass->getAttributes(Injectable::class);
      return !empty($this->lastLoadedAttributes) ? $reflectionClass : null;
    }
    catch (ReflectionException)
    {
      throw new EntryNotFoundException($tokenId);
    }
  }
}