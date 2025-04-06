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
use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
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
use Dotenv\Dotenv;
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
  const string LOCALE_ENV_KEY = 'APP_LOCALE';
  const string DEFAULT_LOCALE = 'en';

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
   * @var array<ExceptionFilterInterface> A list of application scoped exception filters
   */
  protected array $exceptionFilters = [];
  /**
   * @var TemplateEngineInterface $templateEngine The template engine.
   */
  protected TemplateEngineInterface $templateEngine;
  /**
   * @var float $startupTime The time the app started.
   */
  protected float $startupTime = 0;
  /**
   * @var bool $isDebug Determines if the app is in debug mode.
   */
  protected bool $isDebug = false;
  /**
   * @var bool $withProfiling Determines if the app is profiling.
   */
  protected bool $withProfiling = false;
  /**
   * @var array $profileResults The results of the profiling.
   */
  protected array $profileResults = [];
  protected int $profilePrecision = 4;

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
    $this->initializeErrorAndExceptionHandlers();
    $this->initializeAppProperties();
    if ($this->withProfiling) {
      $this->startupTime = microtime(true);
    }
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
  public function useGlobalFilters(ExceptionFilterInterface|string|array $filters): self
  {
    $this->exceptionFilters = array_merge($this->exceptionFilters, (is_array($filters) ? $filters : [$filters]));
    $this->router->addGlobalFilters($this->exceptionFilters);
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function setLogger(LoggerInterface $logger): static
  {
    $this->logger = $logger;
    register_dependency(LoggerInterface::class, $logger);
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

        $this->profileResults = [];
        if ($this->withProfiling) {
          $time = $this->startupTime;
          $this->profileResults['Startup'] = microtime(true) - $time;
        }
        $this->resolveModules();
        if ($this->withProfiling) {
          $this->profileResults['Module Resolution'] = microtime(true) - $time;
          $time = microtime(true);
        }
        $this->resolveProviders();
        if ($this->withProfiling) {
          $this->profileResults['Provider Resolution'] = microtime(true) - $time;
          $time = microtime(true);
        }
        $this->resolveDeclarations();
        if ($this->withProfiling) {
          $this->profileResults['Declaration Resolution'] = microtime(true) - $time;
          $time = microtime(true);
        }
        $this->resolveControllers();
        if ($this->withProfiling) {
          $this->profileResults['Constroller Resolution'] = microtime(true) - $time;
          $time = microtime(true);
        }
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
    if ($this->withProfiling) {
      $this->profileResults['Total Time'] = microtime(true) - $this->startupTime;
      $this->tabulate('Profile Results', ['Stage', 'Duration (in seconds)'], $this->profileResults);
    }
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

  /**
   * Returns the current locale.
   *
   * @return string The current locale.
   */
  public static function getLocale(): string
  {
    $locale = getenv(self::LOCALE_ENV_KEY);

    if (!$locale) {
      return self::DEFAULT_LOCALE;
    }

    return $locale;
  }

  /**
   * Set the current locale.
   *
   * @param string $locale The locale to set.
   * @return void
   */
  public static function setLocale(string $locale): void
  {
    putenv(self::LOCALE_ENV_KEY . '=' . $locale);
  }

  /**
   * Determine if the current locale is the given locale.
   *
   * @param string $locale The locale to check against.
   * @return bool True if the current locale is the given locale, false otherwise.
   */
  public static function isLocale(string $locale): bool
  {
    return self::getLocale() === $locale;
  }

  /**
   * Initialize the error and exception handlers.
   *
   * @return void
   */
  protected function initializeErrorAndExceptionHandlers(): void
  {
    $this->setLogger(new ConsoleLogger(new ConsoleOutput()));
    $this->exceptionHandler = new WhoopsExceptionHandler($this->logger);
    $this->errorHandler = new WhoopsErrorHandler($this->logger);
    $this->httpExceptionHandler = new HttpExceptionHandler($this->logger);

    set_exception_handler(function (Throwable $exception) {
      foreach ($this->exceptionFilters as $type => $filter) {
        if (is_a($exception, $type) ) {
          $filter->catch($exception);
        }
      }

      if (Environment::isProduction()) {
        $this->httpExceptionHandler->handle($exception);
      } else {
        $this->exceptionHandler->handle($exception);
      }
    });

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      $this->errorHandler->handle($errno, $errstr, $errfile, $errline);
    });
  }

  /**
   * Initialize the application properties.
   *
   * @return void
   */
  protected function initializeAppProperties(): void
  {
    $dotEnv = Dotenv::createImmutable(getcwd());
    $dotEnv->load();

    if (!self::getLocale()) {
      self::setLocale(self::DEFAULT_LOCALE);
    }
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

    $this->isDebug = env('DEBUG_MODE', false);
    $this->withProfiling = env('PROFILING', false);
  }

  /**
   *
   * @param string $title
   * @param array $headings
   * @param array $data
   * @return void
   */
  protected function tabulate(string $title, array $headings, array $data): void
  {
    $columnLength = 31;
    $this->logger?->alert('+---------------------------------+---------------------------------+');
    $this->logger?->alert('| ' . str_pad($headings[0], $columnLength, ' ', STR_PAD_RIGHT) . ' | ' . str_pad($headings[1], $columnLength, ' ', STR_PAD_RIGHT) . ' |');
    $this->logger?->alert('+---------------------------------+---------------------------------+');

    foreach ($data as $key => $value) {
      if ($key === 'Total Time') {
        $this->logger?->alert('+---------------------------------+---------------------------------+');
      }
      $this->logger?->alert('| ' . str_pad($key, $columnLength, ' ', STR_PAD_RIGHT) . ' | ' . str_pad(round($value, $this->profilePrecision), $columnLength, ' ', STR_PAD_RIGHT) . ' |');
    }
    $this->logger?->alert('+---------------------------------+---------------------------------+');
  }
}