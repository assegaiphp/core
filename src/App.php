<?php

namespace Assegai\Core;

use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Http\Request;
use Assegai\Core\Responses\Responder;
use Assegai\Core\Responses\Response;
use Exception;
use ReflectionAttribute;
use ReflectionException;

/**
 * @since 1.0.0
 * @version 1.0.0
 * @author Andrew Masiye <amasiye313@gmail.com>
 *
 * @link https://docs.assegaiphp.com
 */
class App
{
  /**
   * @var ReflectionAttribute[] $modules
   */
  protected array $modules = [];
  protected array $providers = [];
  protected array $controllers = [];
  protected array $controllerMap = [];
  protected ?AppConfig $config = null;

  protected ArgumentsHost $host;
  protected Request $request;
  protected Response $response;
  protected Responder $responder;

  /**
   * @param string $rootModuleClass
   * @param Router $router
   * @param ControllerManager $controllerManager
   * @param ModuleManager $moduleManager
   * @param Injector $injector
   */
  public function __construct(
    protected readonly string $rootModuleClass,
    protected readonly Router $router,
    protected readonly ControllerManager $controllerManager,
    protected readonly ModuleManager $moduleManager,
    protected readonly Injector $injector
  )
  {
    $this->config = new AppConfig();
    $this->host = new ArgumentsHost();

    $this->request = $this->host->switchToHttp()->getRequest();
    $this->request->setApp($this);
    $this->response = $this->host->switchToHttp()->getResponse();

    $this->responder = Responder::getInstance();
  }

  /**
   * @param AppConfig|array|null $config
   * @return $this
   */
  public function configure(null|AppConfig|array $config = null): App
  {
    if ($config instanceof  AppConfig)
    {
      $this->config = $config;
    }

    return $this;
  }

  /**
   * @return void
   */
  public function run(): void
  {
    try
    {
      $this->resolveModules();
      $this->resolveProviders();
      $this->resolveControllers();
      $this->handleRequest();
      $this->handleResponse();
    }
    catch(HttpException $exception)
    {
      echo $exception;
    }
    catch (Exception $exception)
    {
      echo new HttpException($exception->getMessage());
    }
  }

  /**
   * @return void
   * @throws HttpException
   */
  public function resolveModules(): void
  {
    $this->modules = $this->moduleManager->buildModuleTokensList(rootToken: $this->rootModuleClass);
  }

  /**
   * @return void
   * @throws EntryNotFoundException
   */
  private function resolveProviders(): void
  {
    $this->providers = $this->moduleManager->buildProviderTokensList();
  }

  /**
   * @return void
   * @throws EntryNotFoundException
   */
  private function resolveControllers(): void
  {
    $this->controllers = $this->controllerManager->buildControllerTokensList($this->moduleManager->getModuleTokens());
    $this->controllerMap = $this->controllerManager->getControllerPathTokenIdMap();
  }

  /**
   * @return void
   * @throws Exceptions\Container\ContainerException
   * @throws Exceptions\Http\NotFoundException
   * @throws HttpException
   * @throws ReflectionException
   */
  private function handleRequest(): void
  {
    $this->request = $this->router->route(url: $this->request->getUri());
    $activatedController =
      $this->router->getActivatedController(request: $this->request, controllerTokensList: $this->controllers);
    $this->response = $this->router->handleRequest(request: $this->request, controller: $activatedController);
  }

  /**
   * Responds to the client's request.
   * @return void
   */
  private function handleResponse(): void
  {
    $this->responder->respond(response: $this->response);
  }
}