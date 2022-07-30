<?php /** @noinspection ALL */

namespace Assegai\Core;

use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Responder;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\IConsumer;
use Assegai\Core\Routing\Router;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Throwable;

//use Psr\Log\LoggerAwareInterface;
//use Psr\Log\LoggerInterface;

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
  /**
   * @var ReflectionClass[] $providers
   */
  protected array $controllers = [];
  /**
   * @var string[] $providers
   */
  protected array $providers = [];
  /**
   * @var array
   */
  protected array $controllerMap = [];
  /**
   * @var AppConfig|null
   */
  protected ?AppConfig $config = null;

  /**
   * @var ArgumentsHost
   */
  protected ArgumentsHost $host;
  /**
   * @var Request
   */
  protected Request $request;
  /**
   * @var Response
   */
  protected Response $response;
  /**
   * @var Responder
   */
  protected Responder $responder;
  /**
   * @var object|null
   */
  protected ?object $activatedController;
  /**
   * @var LoggerInterface|null
   */
  protected ?LoggerInterface $logger = null;

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
    set_exception_handler(function (Throwable $exception) {
      if ($exception instanceof HttpException)
      {
        echo $exception;
      }
      else
      {
        $status = HttpStatus::fromInt(500);
        http_response_code($status->code);

        $response = Config::environment() === EnvironmentType::PRODUCTION
          ? [
            'statusCode' => $status->code,
            'message' => $status->name,
          ]
          : [
            'statusCode' => $status->code,
            'message' =>  $exception->getMessage(),
            'error' => $status->name,
          ];
        echo json_encode($response);
      }
    });
    $this->config = new AppConfig();
    $this->host = new ArgumentsHost();

    $this->request = $this->host->switchToHttp()->getRequest();
    $this->request->setApp($this);
    $this->response = $this->host->switchToHttp()->getResponse();

    $this->responder = Responder::getInstance();
  }

  /**
   * Sets the app configuration to the given configuration properties.
   * @param mixed $config
   * @return $this
   */
  public function configure(mixed $config = null): App
  {
    if ($config instanceof  AppConfig)
    {
      $this->config = $config;
    }

    if ($config instanceof IConsumer)
    {
      // TODO: Complete configuration logic
    }

    return $this;
  }

  /**
   * Sets a logger instance that should be user by the `App` instance.
   *
   * @param LoggerInterface $logger
   * @return void
   */
  public function setLogger(LoggerInterface $logger): void
  {
    $this->logger = $logger;
  }

  /**
   * Runs the current application.
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
   * Determines which modules will be available in the current execution context.
   * @return void
   * @throws HttpException
   */
  public function resolveModules(): void
  {
    $this->modules = $this->moduleManager->buildModuleTokensList(rootToken: $this->rootModuleClass);
  }

  /**
   * Determines which providers will be available in the current execution context.
   * @return void
   * @throws EntryNotFoundException
   */
  private function resolveProviders(): void
  {
    $this->providers = $this->moduleManager->buildProviderTokensList();
  }

  /**
   * Determines which controllers will be available in the current execution context.
   * @return void
   * @throws EntryNotFoundException
   */
  private function resolveControllers(): void
  {
    $this->controllers = $this->controllerManager->buildControllerTokensList($this->moduleManager->getModuleTokens());
    $this->controllerMap = $this->controllerManager->getControllerPathTokenIdMap();
  }

  /**
   * Processes the incoming client request.
   * @return void
   * @throws ContainerException
   * @throws NotFoundException
   * @throws HttpException
   * @throws ReflectionException
   */
  private function handleRequest(): void
  {
    $this->request = $this->router->route();
    $this->activatedController =
      $this->router->getActivatedController(request: $this->request, controllerTokensList: $this->controllers);
  }

  /**
   * Responds to the client's request.
   * @return void
   * @throws ContainerException
   * @throws EntryNotFoundException
   * @throws NotFoundException
   * @throws ReflectionException
   */
  private function handleResponse(): void
  {
    $this->response = $this->router->handleRequest(request: $this->request, controller: $this->activatedController);
    $this->responder->respond(response: $this->response);
  }
}