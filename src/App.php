<?php /** @noinspection ALL */

namespace Assegai\Core;

use Assegai\Core\Enumerations\EnvironmentType;
use Assegai\Core\Enumerations\EventChannel;
use Assegai\Core\Events\Event;
use Assegai\Core\Events\EventManager;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Http\HttpStatus;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Responder;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\IConsumer;
use Assegai\Core\Interfaces\IPipeTransform;
use Assegai\Core\Routing\Router;
use Assegai\Core\Util\Paths;
use Assegai\Core\Util\Debug\Log;
use Exception;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Throwable;

//use Psr\Log\LoggerAwareInterface;
//use Psr\Log\LoggerInterface;

require __DIR__ . '/Util/Definitions.php';

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
   * @var ReflectionClass[] $providers A list of all the imported module tokens
   */
  protected array $controllers = [];
  /**
   * @var array
   */
  protected array $controllerMap = [];
  /**
   * @var AppConfig|null The application configuration.
   */
  protected ?AppConfig $config = null;
  /**
   * @var ArgumentsHost The arguments host.
   */
  protected ArgumentsHost $host;
  /**
   * @var Request The in comming HTTP request.
   */
  protected Request $request;
  /**
   * @var Response The outgoing HTTP response.
   */
  protected Response $response;
  /**
   * @var Responder The response handler.
   */
  protected Responder $responder;
  /**
   * @var object|null The activated controller.
   */
  protected ?object $activatedController;
  /**
   * @var LoggerInterface|null The logger instance.
   */
  protected ?LoggerInterface $logger = null;

  /**
   * @var array A list of application scoped pipes
   */
  protected array $pipes = [];

  /**
   * Constructs an App instance.
   *
   * @param string $rootModuleClass The root module class.
   * @param Router $router The router.
   * @param ControllerManager $controllerManager The controller manager.
   * @param ModuleManager $moduleManager The module manager.
   * @param Injector $injector The injector.
   */
  public function __construct(
    protected readonly string $rootModuleClass,
    protected readonly Router $router,
    protected readonly ControllerManager $controllerManager,
    protected readonly ModuleManager $moduleManager,
    protected readonly Injector $injector
  )
  {
    EventManager::broadcast(EventChannel::APP_INIT_START, new Event());
    set_exception_handler(function (Throwable $exception) {
      if ($exception instanceof HttpException)
      {
        echo $exception;
      }
      else
      {
        $status = HttpStatus::fromInt(500);
        http_response_code($status->code);

        $response = match (Config::environment()) {
          EnvironmentType::PRODUCTION => [
            'statusCode' => $status->code,
            'message' => $status->name,
          ],
          default => [
            'statusCode' => $status->code,
            'message' =>  $exception->getMessage(),
            'error' => $status->name,
          ]
        };
        echo json_encode($response);
      }
    });

    // Initialize app properties
    $this->config = new AppConfig();
    $this->host = new ArgumentsHost();

    $this->request = $this->host->switchToHttp()->getRequest();
    $this->request->setApp($this);
    $this->response = $this->host->switchToHttp()->getResponse();

    $this->responder = Responder::getInstance();
    EventManager::broadcast(EventChannel::APP_INIT_FINISH, new Event($this->host));
  }

  /**
   * Destructs the App.
   */
  public function __destruct()
  {
    EventManager::broadcast(EventChannel::APP_SHUTDOWN_START, new Event());

    EventManager::broadcast(EventChannel::APP_SHUTDOWN_FINISH, new Event());
  }

  /**
   * Sets the app configuration to the given configuration properties.
   *
   * @param mixed $config The configuration properties.
   * @return static
   */
  public function configure(mixed $config = null): static
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
   * Specifies a list of pipes that should be used by the `App` instance.
   *
   * @param IPipeTransform|array $pipes A list of pipes to be used by the `App` instance.
   * @return static The current `App` instance.
   */
  public function useGlobalPipes(IPipeTransform|array $pipes): static
  {
    $this->pipes = array_merge($this->pipes, (is_array($pipes) ? $pipes : [$pipes]));
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
   *
   * @return void
   */
  public function run(): void
  {
    EventManager::broadcast(EventChannel::APP_LISTENING_START, new Event($this->host));
    try
    {
      $resourcePath = Paths::getPublicPath($_SERVER['REQUEST_URI']);

      if (is_file($resourcePath) && !preg_match('/index.(htm|html|php|xhtml)$/', $resourcePath))
      {
        $mimeType = Paths::getMimeType($resourcePath);

        header("Content-Type: $mimeType");
        require_once($resourcePath);
      }
      else
      {
        session_start();
        EventManager::broadcast(EventChannel::SESSION_START, new Event());
        $this->resolveModules();
        $this->resolveProviders();
        $this->resolveDeclarations();
        $this->resolveControllers();
        $this->handleRequest();
      }
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
   *
   * @return void
   * @throws HttpException If the module is not found.
   */
  private function resolveModules(): void
  {
    EventManager::broadcast(EventChannel::MODULE_RESOLUTION_START, new Event());
    $this->moduleManager->buildModuleTokensList(rootToken: $this->rootModuleClass);
    EventManager::broadcast(EventChannel::MODULE_RESOLUTION_FINISH, new Event($this->getModuleTokens()));
  }

  /**
   * Determines which providers will be available in the current execution context.
   *
   * @return void
   * @throws EntryNotFoundException
   */
  private function resolveProviders(): void
  {
    EventManager::broadcast(EventChannel::PROVIDER_RESOLUTION_START, new Event());
    $this->moduleManager->buildProviderTokensList();
    EventManager::broadcast(EventChannel::PROVIDER_RESOLUTION_FINISH, new Event($this->getProviderTokens()));
  }

  /**
   * Determines which declarations will be available in the current execution context.
   *
   * @return void
   */
  private function resolveDeclarations(): void
  {
    EventManager::broadcast(EventChannel::DECLARATION_RESOLUTION_START, new Event());
    $this->moduleManager->buildDeclarationMap();
    EventManager::broadcast(EventChannel::DECLARATION_RESOLUTION_FINISH, new Event());
  }

  /**
   * Determines which controllers will be available in the current execution context.
   * @return void
   * @throws EntryNotFoundException
   */
  private function resolveControllers(): void
  {
    EventManager::broadcast(EventChannel::CONTROLLER_RESOLUTION_START, new Event());
    $this->controllers = $this->controllerManager->buildControllerTokensList($this->getModuleTokens());
    $this->controllerMap = $this->controllerManager->getControllerPathTokenIdMap();
    EventManager::broadcast(EventChannel::CONTROLLER_RESOLUTION_FINISH, new Event([$this->controllers, $this->controllerMap]));
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
    EventManager::broadcast(EventChannel::REQUEST_HANDLING_START, new Event());
    $this->request = $this->router->getRequest();
    EventManager::broadcast(EventChannel::CONTROLLER_WILL_ACTIVATE, new Event());
    $this->activatedController =
      $this->router->getActivatedController(request: $this->request, controllerTokensList: $this->controllers);
    EventManager::broadcast(EventChannel::CONTROLLER_DID_ACTIVATE, new Event($this->activatedController));
    EventManager::broadcast(EventChannel::REQUEST_HANDLING_FINISH, new Event($this->request));
    EventManager::broadcast(EventChannel::RESPONSE_START, new Event());
    $this->response = $this->router->handleRequest(request: $this->request, controller: $this->activatedController);
    $this->respond();
  }

  /**
   * Responds to the client's request.
   * @return void
   * @throws ContainerException
   * @throws EntryNotFoundException
   * @throws NotFoundException
   * @throws ReflectionException
   */
  private function respond(): void
  {
    EventManager::broadcast(EventChannel::RESPONSE_FINISH, new Event());
    $this->responder->respond(response: $this->response);
  }

  /**
   * @return ReflectionAttribute[]
   */
  private function getModuleTokens(): array
  {
    return $this->moduleManager->getModuleTokens();
  }

  /**
   * @return string[]
   */
  private function getProviderTokens(): array
  {
    return $this->moduleManager->getProviderTokens();
  }
}