<?php /** @noinspection ALL */

namespace Assegai\Core;

use Assegai\Core\Config\AppConfig;
use Assegai\Core\Config\ComposerConfig;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Enumerations\EventChannel;
use Assegai\Core\Events\Event;
use Assegai\Core\Events\EventManager;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Handlers\HttpExceptionHandler;
use Assegai\Core\Exceptions\Handlers\WhoopsErrorHandler;
use Assegai\Core\Exceptions\Handlers\WhoopsExceptionHandler;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Exceptions\Interfaces\ErrorHandlerInterface;
use Assegai\Core\Exceptions\Interfaces\ExceptionHandlerInterface;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Responders\Responder;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Assegai\Core\Interfaces\ConsumerInterface;
use Assegai\Core\Interfaces\IPipeTransform;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Rendering\Interfaces\TemplateEngineInterface;
use Assegai\Core\Routing\Router;
use Assegai\Core\Util\Debug\Log;
use Assegai\Core\Util\Paths;
use Error;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

//use Psr\Log\LoggerAwareInterface;

require __DIR__ . '/Util/Definitions.php';

/**
 * @since 1.0.0
 * @version 1.0.0
 * @author Andrew Masiye <amasiye313@gmail.com>
 *
 * @link https://docs.assegaiphp.com
 */
class App implements AppInterface
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
  protected ?AppConfig $appConfig = null;
  /**
   * @var ComposerConfig|null The composer configuration.
   */
  protected ?ComposerConfig $composerConfig = null;
  /**
   * @var ProjectConfig|null The project configuration.
   */
  protected ?ProjectConfig $projectConfig = null;
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
   * @var ErrorHandlerInterface $errorHandler The error handler.
   */
  protected ErrorHandlerInterface $errorHandler;
  /**
   * @var ExceptionHandlerInterface $exceptionHandler The exception handler.
   */
  protected ExceptionHandlerInterface $exceptionHandler;
  /**
   * @var ExceptionHandlerInterface $httpExceptionHandler The HTTP exception handler.
   */
  protected ExceptionHandlerInterface $httpExceptionHandler;
  /**
   * @var array<IPipeTransform> A list of application scoped pipes
   */
  protected array $pipes = [];
  /**
   * @var array<IAssegaiInterceptor> A list of application scoped interceptors
   */
  protected array $interceptors = [];
  /**
   * @var TemplateEngineInterface $templateEngine The template engine.
   */
  protected TemplateEngineInterface $templateEngine;

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
    broadcast(EventChannel::APP_INIT_START, new Event());
    $this->setLogger(new ConsoleLogger(new ConsoleOutput()));
    $this->exceptionHandler = new WhoopsExceptionHandler($this->logger);
    $this->errorHandler = new WhoopsErrorHandler($this->logger);
    $this->httpExceptionHandler = new HttpExceptionHandler($this->logger);

    set_exception_handler(function (Throwable $exception) {
      if (Environment::isProduction()) {
        $this->httpExceptionHandler->handle($exception);
      } else {
        $this->exceptionHandler->handle($exception);
      }
    });

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      $this->errorHandler->handle($errno, $errstr, $errfile, $errline);
    });

    // Initialize app properties
    $this->appConfig = new AppConfig();
    $this->composerConfig = new ComposerConfig();
    $this->projectConfig = new ProjectConfig();
    $this->host = new ArgumentsHost();
    $this->templateEngine = new DefaultTemplateEngine([
      'root_module_class' => $this->rootModuleClass,
      'router' => $this->router,
      'module_manager' => $this->moduleManager,
      'controller_manager' => $this->controllerManager,
      'injector' => $this->injector,
    ]);
    Log::init();

    $this->request = $this->host->switchToHttp()->getRequest();
    $this->request->setApp($this);
    $this->response = $this->host->switchToHttp()->getResponse();

    $this->responder = Responder::getInstance();
    $this->responder->setTemplateEngine($this->templateEngine);
    $this->moduleManager->setRootModuleClass($this->rootModuleClass);
    broadcast(EventChannel::APP_INIT_FINISH, new Event($this->host));
  }

  /**
   * Destructs the App.
   */
  public function __destruct()
  {
    broadcast(EventChannel::APP_SHUTDOWN_START, new Event());

    // Decommission app properties

    broadcast(EventChannel::APP_SHUTDOWN_FINISH, new Event());
  }

  /**
   * @inheritDoc
   */
  public function configure(mixed $config = null): static
  {
    if ($config instanceof  ProjectConfig) {
      $this->appConfig = $config;
    }

    if ($config instanceof ConsumerInterface) {
      // TODO: Complete configuration logic
    }

    return $this;
  }

  /**
   * @inheritDoc
   */
  public function useGlobalPipes(IPipeTransform|array $pipes): self
  {
    $this->pipes = array_merge($this->pipes, (is_array($pipes) ? $pipes : [$pipes]));
    $this->router->addGlobalPipes($this->pipes);
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function useGlobalInterceptors(IAssegaiInterceptor|string|array $interceptors): self
  {
    $this->interceptors = array_merge($this->interceptors, (is_array($interceptors) ? $interceptors : [$interceptors]));
    $this->router->addGlobalInterceptors($this->interceptors);
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function setLogger(LoggerInterface $logger): static
  {
    $this->logger = $logger;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function run(): void
  {
    broadcast(EventChannel::APP_LISTENING_START, new Event($this->host));
    try {
      $resourcePath = Paths::getPublicPath($_SERVER['REQUEST_URI']);

      if (is_file($resourcePath) && !preg_match('/index.(htm|html|php|xhtml)$/', $resourcePath)) {
        $mimeType = Paths::getMimeType($resourcePath);

        header("Content-Type: $mimeType");
        require_once($resourcePath);
      } else {
        $sessionLimiter = $this->appConfig->get('session.limit', null);
        if (! in_array($sessionLimiter, [null, 'public', 'private_no_expire', 'private', 'nocache'])) {
          $sessionLimiter = null;
        }

        $sessionExpire = $this->appConfig->get('session.expire', null);
        if (!is_numeric($sessionExpire)) {
          $sessionExpire = null;
        } else {
          $sessionExpire = (int)$sessionExpire;
        }

        session_cache_limiter($sessionLimiter);
        session_cache_expire($sessionExpire);

        session_start();
        broadcast(EventChannel::SESSION_START, new Event());
        $this->resolveModules();
        $this->resolveProviders();
        $this->resolveDeclarations();
        $this->resolveControllers();
        $this->handleRequest();
      }
    } catch (Throwable $exception) {
      if (Environment::isProduction()) {
        $this->httpExceptionHandler->handle($exception);
      } else {
        $this->exceptionHandler->handle($exception);
      }
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
    broadcast(EventChannel::MODULE_RESOLUTION_START, new Event());
    $this->moduleManager->buildModuleTokensList(rootToken: $this->rootModuleClass);
    broadcast(EventChannel::MODULE_RESOLUTION_FINISH, new Event($this->getModuleTokens()));
  }

  /**
   * Determines which providers will be available in the current execution context.
   *
   * @return void
   * @throws EntryNotFoundException
   */
  private function resolveProviders(): void
  {
    broadcast(EventChannel::PROVIDER_RESOLUTION_START, new Event());
    $this->moduleManager->buildProviderTokensList();
    broadcast(EventChannel::PROVIDER_RESOLUTION_FINISH, new Event($this->getProviderTokens()));
  }

  /**
   * Determines which declarations will be available in the current execution context.
   *
   * @return void
   * @throws HttpException
   */
  private function resolveDeclarations(): void
  {
    broadcast(EventChannel::DECLARATION_RESOLUTION_START, new Event());
    $this->moduleManager->buildDeclarationMap();
    broadcast(EventChannel::DECLARATION_RESOLUTION_FINISH, new Event());
  }

  /**
   * Determines which controllers will be available in the current execution context.
   * @return void
   * @throws EntryNotFoundException
   */
  private function resolveControllers(): void
  {
    broadcast(EventChannel::CONTROLLER_RESOLUTION_START, new Event());
    $this->controllers = $this->controllerManager->buildControllerTokensList($this->getModuleTokens());
    $this->controllerMap = $this->controllerManager->getControllerPathTokenIdMap();
    broadcast(EventChannel::CONTROLLER_RESOLUTION_FINISH, new Event([$this->controllers, $this->controllerMap]));
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
    broadcast(EventChannel::REQUEST_HANDLING_START, new Event());
    $this->request = $this->router->getRequest();
    broadcast(EventChannel::CONTROLLER_WILL_ACTIVATE, new Event());
    $this->activatedController =
      $this->router->getActivatedController(request: $this->request, controllerTokensList: $this->controllers);
    broadcast(EventChannel::CONTROLLER_DID_ACTIVATE, new Event($this->activatedController));
    broadcast(EventChannel::REQUEST_HANDLING_FINISH, new Event($this->request));
    broadcast(EventChannel::RESPONSE_START, new Event());
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
    broadcast(EventChannel::RESPONSE_FINISH, new Event());
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