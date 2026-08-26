<?php /** @noinspection ALL */

namespace Assegai\Core;

use Assegai\Core\ApiDocs\OpenApiGenerator;
use Assegai\Core\ApiDocs\SwaggerUiRenderer;
use Assegai\Core\Config\AppConfig;
use Assegai\Core\Config\ComposerConfig;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\Consumers\ExceptionFiltersConsumer;
use Assegai\Core\Consumers\MiddlewareConsumer;
use Assegai\Core\Enumerations\EventChannel;
use Assegai\Core\Enumerations\Http\ContentType;
use Assegai\Core\Events\Event;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Handlers\DefaultErrorHandler;
use Assegai\Core\Exceptions\Handlers\HttpExceptionHandler;
use Assegai\Core\Exceptions\Handlers\WhoopsErrorHandler;
use Assegai\Core\Exceptions\Handlers\WhoopsExceptionHandler;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\InternalServerErrorException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Exceptions\Filters\ExceptionFilterBinding;
use Assegai\Core\Exceptions\Filters\ExceptionFilterMetadata;
use Assegai\Core\Exceptions\Interfaces\ErrorHandlerInterface;
use Assegai\Core\Exceptions\Interfaces\ExceptionFilterInterface;
use Assegai\Core\Exceptions\Interfaces\ExceptionHandlerInterface;
use Assegai\Core\Http\Cors\CorsFileResponseEmitter;
use Assegai\Core\Http\Cors\CorsOptions;
use Assegai\Core\Http\Cors\CorsProcessor;
use Assegai\Core\Http\Cors\CorsResponseEmitter;
use Assegai\Core\Http\Requests\Interfaces\RequestInterface;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Requests\RuntimeRequestContext;
use Assegai\Core\Http\Responses\Emitters\PhpResponseEmitter;
use Assegai\Core\Http\Responses\Interfaces\FileResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponderInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseEmitterInterface;
use Assegai\Core\Http\Responses\Interfaces\ResponseInterface;
use Assegai\Core\Http\Responses\Responders\Responder;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Interfaces\AppInterface;
use Assegai\Core\Interfaces\HttpRuntimeInterface;
use Assegai\Core\Interfaces\IAssegaiInterceptor;
use Assegai\Core\Interfaces\IPipeTransform;
use Assegai\Core\Interfaces\OnApplicationBootstrapInterface;
use Assegai\Core\Interfaces\OnApplicationShutdownInterface;
use Assegai\Core\Interfaces\OnModuleInitInterface;
use Assegai\Core\Rendering\Engines\DefaultTemplateEngine;
use Assegai\Core\Rendering\Interfaces\TemplateEngineInterface;
use Assegai\Core\Queues\QueueFactory;
use Assegai\Core\Routing\Router;
use Assegai\Core\Runtimes\PhpHttpRuntime;
use Assegai\Core\Runtimes\RuntimeContext;
use Assegai\Core\Util\Debug\Log;
use Assegai\Core\Util\Paths;
use Closure;
use Dotenv\Dotenv;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

require_once __DIR__ . '/Util/Definitions.php';

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
    protected ?object $activatedController = null;
    /**
     * @var LoggerInterface|null The logger instance.
     */
    protected ?LoggerInterface $logger = null;
    /**
     * @var ErrorHandlerInterface $errorHandler The error handler.
     */
    protected ErrorHandlerInterface $errorHandler;
    /**
     * @var ErrorHandlerInterface $productionErrorHandler The production-safe error handler.
     */
    protected ErrorHandlerInterface $productionErrorHandler;
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
    protected ExceptionFiltersConsumer $exceptionFiltersConsumer;
    /**
     * @var MiddlewareConsumer|null The middleware consumer
     */
    protected ?MiddlewareConsumer $middlewareConsumer = null;
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
    protected bool $sessionStartedForRequest = false;
    protected ?string $pendingSessionCookieHeader = null;
    protected bool $applicationGraphPrepared = false;
    protected bool $middlewarePrepared = false;
    protected bool $moduleInitInvoked = false;
    protected bool $applicationBootstrapInvoked = false;
    protected bool $applicationShutdownInvoked = false;
    protected int $applicationGraphBuildCount = 0;
    protected int $middlewareBuildCount = 0;
    protected ?array $lifecycleTargets = null;
    protected ?RuntimeRequestContext $runtimeRequestContext = null;
    protected ?ResponseEmitterInterface $runtimeResponseEmitter = null;
    /** @var CorsOptions|Closure|null */
    protected CorsOptions|Closure|null $corsOptions = null;
    protected ?CorsOptions $activeCorsOptions = null;
    protected ?ResponseEmitterInterface $activeResponseEmitter = null;
    protected HttpRuntimeInterface $runtime;
    protected ResponseEmitterInterface $responseEmitter;

    /**
     * Constructs an App instance.
     *
     * @param string $rootModuleClass The root module class.
     * @param Router $router The router.
     * @param ControllerManager $controllerManager The controller manager.
     * @param ModuleManager $moduleManager The module manager.
     * @param Injector $injector The injector.
     * @param HttpRuntimeInterface|null $runtime The HTTP runtime adapter.
     */
    public function __construct(
        protected readonly string            $rootModuleClass,
        protected readonly Router            $router,
        protected readonly ControllerManager $controllerManager,
        protected readonly ModuleManager     $moduleManager,
        protected readonly Injector          $injector,
        ?HttpRuntimeInterface                $runtime = null,
    )
    {
        $this->runtime = $runtime ?? new PhpHttpRuntime();
        $this->exceptionFiltersConsumer = new ExceptionFiltersConsumer($this->injector);
        broadcast(EventChannel::APP_INIT_START, new Event());
        $this->initializeErrorAndExceptionHandlers();
        $this->initializeAppProperties();
        if ($this->withProfiling) {
            $this->startupTime = microtime(true);
        }
        broadcast(EventChannel::APP_INIT_FINISH, new Event($this->host));
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
        $this->productionErrorHandler = new DefaultErrorHandler($this->logger);
        $this->httpExceptionHandler = new HttpExceptionHandler($this->logger);

        set_exception_handler(fn(Throwable $exception) => $this->handleThrowable($exception));

        set_error_handler(function ($errno, $errstr, $errfile, $errline): bool {
            if ((error_reporting() & $errno) === 0) {
                return false;
            }

            return $this->handlePhpError($errno, $errstr, $errfile, $errline);
        });
    }

    /**
     * Handles PHP runtime errors through the appropriate environment-specific handler.
     */
    protected function handlePhpError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (Environment::isProduction()) {
            $this->productionErrorHandler->handle($errno, $errstr, $errfile, $errline);
            return true;
        }

        $this->errorHandler->handle($errno, $errstr, $errfile, $errline);
        return true;
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
     * Initialize the application properties.
     *
     * @return void
     */
    protected function initializeAppProperties(): void
    {
        $dotEnv = Dotenv::createImmutable(Paths::getWorkingDirectory());
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
        $this->responseEmitter = new PhpResponseEmitter();
        $this->responder = Responder::create();
        $this->responder->setTemplateEngine($this->templateEngine);
        $this->responder->setEmitter($this->responseEmitter);
        $this->moduleManager->setRootModuleClass($this->rootModuleClass);
        $this->registerApplicationDependencies();
        $this->refreshRequestScope();

        $this->isDebug = Config::isDebug();
        $this->withProfiling = env('PROFILING', false);
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
     * Registers application-scoped framework services so they can always be injected.
     *
     * @return void
     */
    protected function registerApplicationDependencies(): void
    {
        $dependencies = [
            self::class => $this,
            AppInterface::class => $this,
            AppConfig::class => $this->appConfig,
            ComposerConfig::class => $this->composerConfig,
            ProjectConfig::class => $this->projectConfig,
            Session::class => Session::getInstance(),
            ArgumentsHost::class => $this->host,
            Router::class => $this->router,
            ControllerManager::class => $this->controllerManager,
            ModuleManager::class => $this->moduleManager,
            Injector::class => $this->injector,
            TemplateEngineInterface::class => $this->templateEngine,
            LoggerInterface::class => $this->logger,
        ];

        if ($this->templateEngine instanceof DefaultTemplateEngine) {
            $dependencies[DefaultTemplateEngine::class] = $this->templateEngine;
        }

        foreach ($dependencies as $entryId => $dependency) {
            if (null !== $dependency) {
                $this->injector->add($entryId, $dependency);
            }
        }
    }

    /**
     * Refreshes request-scoped services for the current request cycle.
     *
     * @return void
     */
    protected function refreshRequestScope(): void
    {
        $this->closeSessionForCurrentRequest();
        $this->clearRequestScopedRuntimeContext();
        $this->router->resetRequestState();
        $this->activatedController = null;
        $this->request = $this->runtimeRequestContext instanceof RuntimeRequestContext
            ? Request::createFromRuntimeContext($this->runtimeRequestContext)
            : Request::createFromGlobals();
        $this->request->setApp($this);
        Request::setInstance($this->request);

        $this->response = Response::create();
        Response::setInstance($this->response);
        $this->activeCorsOptions = $this->resolveCorsOptions($this->request);
        $this->activeResponseEmitter = null;

        $this->resetRequestScopedDependencies();
        $this->registerRequestScopedDependencies();
        $this->responder->setEmitter($this->getActiveResponseEmitter());
        $this->sessionStartedForRequest = false;
        $this->pendingSessionCookieHeader = null;
    }

    /**
     * Closes the active session for the current request so locks do not leak across requests.
     *
     * @return void
     */
    protected function closeSessionForCurrentRequest(): void
    {
        $this->prepareLongLivedRuntimeSessionCookie();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->sessionStartedForRequest = false;
            $this->applyPendingSessionCookieHeader();
            $this->clearLongLivedRuntimeSessionState();
            return;
        }

        session_write_close();
        $this->sessionStartedForRequest = false;
        $this->applyPendingSessionCookieHeader();
        $this->clearLongLivedRuntimeSessionState();
    }

    /**
     * Clears request-scoped runtime context entries after the request cycle completes.
     *
     * RuntimeContext is now the backing store for all request-scoped framework objects
     * and request-scoped userland providers. Flushing the whole active context ensures
     * long-lived runtimes do not leak dependencies across requests.
     *
     * @return void
     */
    protected function clearRequestScopedRuntimeContext(): void
    {
        RuntimeContext::flush();
    }

    /**
     * Clears request-scoped resolved services while retaining application-scoped framework services.
     *
     * @return void
     */
    protected function resetRequestScopedDependencies(): void
    {
        $this->injector->retain([
            self::class,
            AppInterface::class,
            AppConfig::class,
            ComposerConfig::class,
            ProjectConfig::class,
            Session::class,
            ArgumentsHost::class,
            Responder::class,
            ResponderInterface::class,
            ResponseEmitterInterface::class,
            Router::class,
            ControllerManager::class,
            ModuleManager::class,
            Injector::class,
            QueueFactory::class,
            TemplateEngineInterface::class,
            LoggerInterface::class,
            DefaultTemplateEngine::class,
        ]);
    }

    /**
     * Registers request-scoped framework services for the active request.
     *
     * @return void
     */
    protected function registerRequestScopedDependencies(): void
    {
        $this->responder = Responder::create();
        $this->responder->setTemplateEngine($this->templateEngine);
        $this->responder->setEmitter($this->getActiveResponseEmitter());

        RuntimeContext::set(Request::class, $this->request);
        RuntimeContext::set(RequestInterface::class, $this->request);
        RuntimeContext::set(Response::class, $this->response);
        RuntimeContext::set(ResponseInterface::class, $this->response);
        RuntimeContext::set(ResponseEmitterInterface::class, $this->getActiveResponseEmitter());
        RuntimeContext::set(Responder::class, $this->responder);
        RuntimeContext::set(ResponderInterface::class, $this->responder);
    }

    /**
     * Returns the response emitter for the active request cycle.
     *
     * @return ResponseEmitterInterface
     */
    protected function getActiveResponseEmitter(): ResponseEmitterInterface
    {
        if ($this->activeResponseEmitter instanceof ResponseEmitterInterface) {
            return $this->activeResponseEmitter;
        }

        $emitter = $this->runtimeResponseEmitter;

        if (!$emitter instanceof ResponseEmitterInterface) {
            $registeredEmitter = $this->injector->get(ResponseEmitterInterface::class);

            $emitter = $registeredEmitter instanceof ResponseEmitterInterface && $registeredEmitter !== $this->responseEmitter
                ? $registeredEmitter
                : $this->responseEmitter;
        }

        if ($this->activeCorsOptions) {
            $processor = new CorsProcessor($this->activeCorsOptions);
            $emitter = $emitter instanceof FileResponseEmitterInterface
                ? new CorsFileResponseEmitter($emitter, $this->request, $processor)
                : new CorsResponseEmitter($emitter, $this->request, $processor);
        }

        return $this->activeResponseEmitter = $emitter;
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
        if ($config instanceof ProjectConfig) {
            $this->projectConfig = $config;
        }

        if ($config instanceof AppConfig) {
            $this->appConfig = $config;
        }

        if ($config instanceof ComposerConfig) {
            $this->composerConfig = $config;
        }

        if ($config instanceof MiddlewareConsumer) {
            $this->middlewareConsumer = $config;
            $this->invalidateMiddlewareGraph();
        }

        return $this;
    }

    /**
     * Enables application-level Cross-Origin Resource Sharing.
     *
     * A callable receives the active request and may return a CorsOptions instance,
     * an options array, true for defaults, or false/null to disable CORS for that request.
     *
     * @param CorsOptions|array<string, mixed>|callable|null $options
     * @return static
     */
    public function enableCors(CorsOptions|array|callable|null $options = null): static
    {
        $this->corsOptions = is_callable($options)
            ? Closure::fromCallable($options)
            : CorsOptions::from($options);
        $this->activeCorsOptions = null;
        $this->activeResponseEmitter = null;

        return $this;
    }

    private function resolveCorsOptions(RequestInterface $request): ?CorsOptions
    {
        if (!$this->corsOptions instanceof Closure) {
            return $this->corsOptions;
        }

        $options = ($this->corsOptions)($request);

        if ($options === false || is_null($options)) {
            return null;
        }

        if ($options === true) {
            return new CorsOptions();
        }

        if ($options instanceof CorsOptions || is_array($options)) {
            return CorsOptions::from($options);
        }

        throw new InvalidArgumentException(
            'A CORS options callback must return CorsOptions, an options array, true, false, or null.'
        );
    }

    /**
     * Invalidates the cached middleware graph so later requests rebuild it.
     *
     * @return void
     */
    protected function invalidateMiddlewareGraph(): void
    {
        $this->middlewarePrepared = false;
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
    public function useGlobalFilters(ExceptionFilterInterface|string|array $filters, string|array $type = Exception::class): self
    {
        $types = ExceptionFilterMetadata::normalize($type);
        $bindings = [];

        foreach ($this->normalizeExceptionFilters($filters) as $filter) {
            $bindings[] = new ExceptionFilterBinding($filter, $types);
        }

        $this->router->addGlobalFilters($bindings);
        return $this;
    }

    /**
     * @param class-string<ExceptionFilterInterface>|ExceptionFilterInterface|array<mixed> $filters
     * @return array<int, class-string<ExceptionFilterInterface>|ExceptionFilterInterface>
     */
    private function normalizeExceptionFilters(string|ExceptionFilterInterface|array $filters): array
    {
        $normalized = [];

        foreach (is_array($filters) ? $filters : [$filters] as $filter) {
            if (is_array($filter)) {
                $normalized = [...$normalized, ...$this->normalizeExceptionFilters($filter)];
                continue;
            }

            if ($filter instanceof ExceptionFilterInterface) {
                $normalized[] = $filter;
                continue;
            }

            if (is_string($filter) && is_a($filter, ExceptionFilterInterface::class, true)) {
                $normalized[] = $filter;
                continue;
            }

            throw new InvalidArgumentException(
                'Exception filters must be class names or instances of ExceptionFilterInterface.'
            );
        }

        return $normalized;
    }

    /**
     * Replaces the current HTTP runtime.
     *
     * @param HttpRuntimeInterface $runtime
     * @return static
     */
    public function useRuntime(HttpRuntimeInterface $runtime): static
    {
        $this->runtime = $runtime;
        return $this;
    }

    /**
     * Returns the active HTTP runtime.
     *
     * @return HttpRuntimeInterface
     */
    public function getRuntime(): HttpRuntimeInterface
    {
        return $this->runtime;
    }

    /**
     * Binds a runtime-provided request snapshot for the next request cycle.
     *
     * @param RuntimeRequestContext|null $context
     * @return void
     */
    public function setRuntimeRequestContext(?RuntimeRequestContext $context): void
    {
        $this->runtimeRequestContext = $context;
        $this->activeCorsOptions = null;
        $this->activeResponseEmitter = null;
    }

    /**
     * Binds a runtime-specific response emitter for the next request cycle.
     *
     * @param ResponseEmitterInterface|null $emitter
     * @return void
     */
    public function setRuntimeResponseEmitter(?ResponseEmitterInterface $emitter): void
    {
        $this->runtimeResponseEmitter = $emitter;
        $this->activeResponseEmitter = null;
    }

    /**
     * Clears any runtime overrides that were bound for an alternate HTTP adapter.
     *
     * @return void
     */
    public function clearRuntimeOverrides(): void
    {
        $this->runtimeRequestContext = null;
        $this->runtimeResponseEmitter = null;
        $this->activeCorsOptions = null;
        $this->activeResponseEmitter = null;
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        try {
            $this->runtime->run($this, function (): void {
                $this->runDefaultHttpLifecycle();
            });
        } finally {
            $this->shutdown();
        }
    }

    /**
     * Runs the default PHP request lifecycle.
     *
     * @return void
     */
    protected function runDefaultHttpLifecycle(): void
    {
        $this->refreshRequestScope();
        broadcast(EventChannel::APP_LISTENING_START, new Event($this->host));
        try {
            if ($this->handleCorsPreflight()) {
                return;
            }

            $resourcePath = $this->resolvePublicResourcePath($_SERVER['REQUEST_URI'] ?? null);

            if (is_string($resourcePath)) {
                $this->respondWithPublicResource($resourcePath);
                return;
            } else {
                $this->startSessionForCurrentRequest();

                $this->profileResults = [];
                if ($this->withProfiling) {
                    $time = $this->startupTime;
                    $this->profileResults['Startup'] = microtime(true) - $time;
                    $time = microtime(true);
                }
                $this->prepareApplicationGraph();
                if ($this->withProfiling) {
                    $this->profileResults['Application Graph Preparation'] = microtime(true) - $time;
                    $time = microtime(true);
                }
                $this->prepareMiddlewareGraph();
                if ($this->withProfiling) {
                    $this->profileResults['Middleware Preparation'] = microtime(true) - $time;
                    $time = microtime(true);
                }
                if ($this->handleGeneratedApiDocsRequest()) {
                    return;
                }
                $this->handleRequest();
            }
        } catch (Throwable $exception) {
            $this->handleThrowable($exception);
        } finally {
            $this->clearRequestScopedRuntimeContext();
            $this->router->resetRequestState();
            $this->activatedController = null;
        }
    }

    /**
     * Responds to a framework-managed CORS preflight before sessions, routing, or middleware.
     */
    protected function handleCorsPreflight(): bool
    {
        if (!$this->activeCorsOptions) {
            return false;
        }

        $processor = new CorsProcessor($this->activeCorsOptions);

        if (!$processor->shouldShortCircuitPreflight($this->request)) {
            return false;
        }

        $this->response->reset();
        $this->response->setStatus($this->activeCorsOptions->optionsSuccessStatus);
        $this->response->setBody('');
        $this->response->setHeader('Content-Length', '0');
        $this->responder->respond($this->response);

        return true;
    }

    /**
     * Emits a public resource through the runtime-neutral response pipeline.
     *
     * @throws InternalServerErrorException
     */
    protected function respondWithPublicResource(string $resourcePath): void
    {
        $this->response->reset();
        $this->response->setBody('');
        $this->response->setHeader('Content-Type', Paths::getMimeType($resourcePath));
        $this->response->setHeader('X-Content-Type-Options', 'nosniff');
        $contentLength = filesize($resourcePath);

        if (is_int($contentLength) && $contentLength >= 0) {
            $this->response->setHeader('Content-Length', (string)$contentLength);
        }

        $emitter = $this->getActiveResponseEmitter();

        if (!$emitter instanceof FileResponseEmitterInterface) {
            throw new InternalServerErrorException(
                'The active response emitter does not support streaming public resources.'
            );
        }

        $emitter->emitFile($resourcePath, $this->response);
    }

    /**
     * Resolves a request URI to a safe static file inside the public directory.
     *
     * @param string|null $requestUri
     * @return string|false
     */
    protected function resolvePublicResourcePath(?string $requestUri): string|false
    {
        $publicDirectory = realpath(Paths::getPublicPath());

        if ($publicDirectory === false || !is_dir($publicDirectory)) {
            return false;
        }

        if (!is_string($requestUri) || trim($requestUri) === '') {
            return false;
        }

        $requestPath = parse_url($requestUri, PHP_URL_PATH);

        if (!is_string($requestPath) || $requestPath === '' || $requestPath === '/') {
            return false;
        }

        $relativePath = trim(str_replace('\\', '/', ltrim($requestPath, '/')), '/');

        if ($relativePath === '' || $this->containsHiddenPathSegment($relativePath)) {
            return false;
        }

        $resourceCandidate = $publicDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $resourcePath = realpath($resourceCandidate);

        if ($resourcePath === false || !is_file($resourcePath)) {
            return false;
        }

        if (!$this->isPathInsideDirectory($resourcePath, $publicDirectory)) {
            return false;
        }

        if ($this->shouldBypassPublicResourceStreaming($resourcePath, $relativePath)) {
            return false;
        }

        return $resourcePath;
    }

    /**
     * @param string $relativePath
     * @return bool
     */
    protected function containsHiddenPathSegment(string $relativePath): bool
    {
        $segments = array_filter(explode('/', $relativePath), static fn(string $segment): bool => $segment !== '');

        foreach ($segments as $index => $segment) {
            if (str_starts_with($segment, '.') && !($index === 0 && $segment === '.well-known')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $resourcePath
     * @return bool
     */
    protected function shouldBypassPublicResourceStreaming(string $resourcePath, string $relativePath): bool
    {
        $extension = strtolower(pathinfo($resourcePath, PATHINFO_EXTENSION));
        $normalizedRelativePath = trim(str_replace('\\', '/', $relativePath), '/');

        if (in_array($extension, ['php', 'phtml', 'phar', 'inc'], true)) {
            return true;
        }

        if ($extension === '') {
            return !str_starts_with($normalizedRelativePath, '.well-known/');
        }

        $allowedExtensions = [
            '7z',
            'atom',
            'avif',
            'bmp',
            'bz2',
            'css',
            'csv',
            'eot',
            'gif',
            'gz',
            'htm',
            'html',
            'ico',
            'jpeg',
            'jpg',
            'js',
            'json',
            'map',
            'md',
            'mjs',
            'mp3',
            'mp4',
            'mpeg',
            'oga',
            'ogv',
            'ogx',
            'otf',
            'pdf',
            'png',
            'rss',
            'rtf',
            'svg',
            'svgz',
            'tgz',
            'ts',
            'ttf',
            'txt',
            'wasm',
            'wav',
            'weba',
            'webm',
            'webmanifest',
            'webp',
            'woff',
            'woff2',
            'xhtml',
            'xls',
            'xlsx',
            'xml',
            'zip',
        ];

        if (!in_array($extension, $allowedExtensions, true)) {
            return true;
        }

        return in_array(
            strtolower(pathinfo($resourcePath, PATHINFO_BASENAME)),
            ['index.htm', 'index.html', 'index.php', 'index.xhtml'],
            true,
        );
    }

    /**
     * @param string $path
     * @param string $directory
     * @return bool
     */
    protected function isPathInsideDirectory(string $path, string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $directory);
    }

    /**
     * Handles an uncaught throwable through the framework exception pipeline.
     *
     * @param Throwable $exception
     * @return void
     */
    protected function handleThrowable(Throwable $exception): void
    {
        try {
            $handled = $this->exceptionFiltersConsumer->handle(
                $exception,
                $this->host,
                $this->router->getActiveExceptionFilterBindings(),
            );
        } catch (Throwable $filterException) {
            $exception = $filterException;
            $handled = false;
        }

        $this->closeSessionForCurrentRequest();

        if ($handled) {
            $this->response = Response::current();
            $this->responder->respond($this->response);
            return;
        }

        if (Environment::isProduction()) {
            $this->httpExceptionHandler->handle($exception);
            return;
        }

        $this->exceptionHandler->handle($exception);
    }

    /**
     * Routes a runtime-level throwable through the framework error pipeline.
     *
     * Alternate HTTP runtimes can hit this path when a throwable escapes the
     * outer request callback itself. In that case we still want framework error
     * rendering, and we must ensure a request scope exists for the active runtime.
     *
     * @param Throwable $exception
     * @return void
     */
    public function handleRuntimeThrowable(Throwable $exception): void
    {
        try {
            $this->refreshRequestScope();
            $this->handleThrowable($exception);
        } finally {
            $this->clearRequestScopedRuntimeContext();
        }
    }

    /**
     * Prepares reusable application state ahead of request handling.
     *
     * Long-lived runtimes can call this during worker startup so the first request
     * does not pay the full graph construction cost.
     *
     * @return void
     * @throws ContainerException
     * @throws EntryNotFoundException
     * @throws HttpException
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $this->prepareApplicationGraph();
        $this->prepareMiddlewareGraph();
    }

    /**
     * Invokes application shutdown hooks once for the current app instance.
     *
     * @return void
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function shutdown(): void
    {
        $this->invokeApplicationShutdownHooks();
    }

    /**
     * Starts the session for the current request when needed.
     *
     * @return void
     */
    protected function startSessionForCurrentRequest(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_DISABLED) {
            return;
        }

        $sessionName = $this->appConfig?->get('session.name', null);

        if (is_string($sessionName) && $sessionName !== '') {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $sessionName)) {
                throw new InvalidArgumentException('The configured session name is invalid.');
            }

            if (session_name() !== $sessionName && session_name($sessionName) === false) {
                throw new InvalidArgumentException('The configured session name could not be applied.');
            }
        }

        $sessionLimiter = $this->appConfig->get('session.limit', null);

        if (!in_array($sessionLimiter, [null, 'public', 'private_no_expire', 'private', 'nocache'], true)) {
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

        $cookieParams = $this->resolveSessionCookieParameters();
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params($cookieParams);
        ini_set('session.use_strict_mode', '1');

        $isLongLivedRuntime = $this->isLongLivedRuntime();
        $incomingSessionId = null;

        if ($isLongLivedRuntime) {
            $incomingSessionId = $this->resolveIncomingSessionId();
            session_id($incomingSessionId ?? '');
        }

        $sessionOptions = $isLongLivedRuntime
            ? ['use_cookies' => false]
            : [];

        if (session_start($sessionOptions)) {
            $this->sessionStartedForRequest = true;
            broadcast(EventChannel::SESSION_START, new Event());
        }
    }

    private function isLongLivedRuntime(): bool
    {
        return strtolower($this->runtime->getName()) !== 'php';
    }

    private function prepareLongLivedRuntimeSessionCookie(): void
    {
        if (!$this->sessionStartedForRequest || !$this->isLongLivedRuntime()) {
            return;
        }

        $currentSessionId = session_id();

        if (
            !is_string($currentSessionId) ||
            $currentSessionId === '' ||
            hash_equals($this->resolveIncomingSessionId() ?? '', $currentSessionId)
        ) {
            return;
        }

        $this->pendingSessionCookieHeader = $this->buildSessionCookieHeader(
            $currentSessionId,
            $this->resolveSessionCookieParameters()
        );
    }

    private function applyPendingSessionCookieHeader(): void
    {
        if ($this->pendingSessionCookieHeader === null || !isset($this->response)) {
            return;
        }

        $this->response->setHeader('Set-Cookie', $this->pendingSessionCookieHeader, false);
        $this->pendingSessionCookieHeader = null;
    }

    private function clearLongLivedRuntimeSessionState(): void
    {
        if (!$this->isLongLivedRuntime()) {
            return;
        }

        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_id('');
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
        }
    }

    private function resolveIncomingSessionId(): ?string
    {
        $sessionName = session_name();

        if (!is_string($sessionName)) {
            return null;
        }

        $sessionId = $this->request->getCookies($sessionName);

        if (!is_string($sessionId) || !preg_match('/^[A-Za-z0-9,-]{1,128}$/D', $sessionId)) {
            return null;
        }

        return $sessionId;
    }

    /**
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    private function resolveSessionCookieParameters(): array
    {
        $sessionConfig = $this->appConfig?->get('session', []);
        $sessionConfig = is_array($sessionConfig) ? $sessionConfig : [];
        $configuredSecure = $sessionConfig['cookieSecure'] ?? $sessionConfig['cookie_secure'] ?? null;
        $sameSite = ucfirst(strtolower((string)($sessionConfig['cookieSameSite'] ?? $sessionConfig['cookie_samesite'] ?? 'Lax')));

        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }

        $secure = is_bool($configuredSecure)
            ? $configuredSecure
            : $this->request->getProtocol() === 'https';

        if ($sameSite === 'None') {
            $secure = true;
        }

        $path = (string)($sessionConfig['cookiePath'] ?? $sessionConfig['cookie_path'] ?? '/');
        $domain = (string)($sessionConfig['cookieDomain'] ?? $sessionConfig['cookie_domain'] ?? '');

        if ($path === '' || $path[0] !== '/' || preg_match('/[\x00-\x20;,\x7f]/', $path)) {
            $path = '/';
        }

        if ($domain !== '' && !preg_match('/^\.?[a-z0-9.-]+$/iD', $domain)) {
            $domain = '';
        }

        return [
            'lifetime' => max(0, (int)($sessionConfig['cookieLifetime'] ?? $sessionConfig['cookie_lifetime'] ?? 0)),
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => (bool)($sessionConfig['cookieHttpOnly'] ?? $sessionConfig['cookie_httponly'] ?? true),
            'samesite' => $sameSite,
        ];
    }

    /**
     * @param array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string} $parameters
     */
    private function buildSessionCookieHeader(string $sessionId, array $parameters): string
    {
        $parts = [session_name() . '=' . rawurlencode($sessionId)];
        $parts[] = 'Path=' . ($parameters['path'] !== '' ? $parameters['path'] : '/');

        if ($parameters['domain'] !== '') {
            $parts[] = 'Domain=' . $parameters['domain'];
        }

        if ($parameters['lifetime'] > 0) {
            $parts[] = 'Max-Age=' . $parameters['lifetime'];
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', time() + $parameters['lifetime']);
        }

        if ($parameters['secure']) {
            $parts[] = 'Secure';
        }

        if ($parameters['httponly']) {
            $parts[] = 'HttpOnly';
        }

        $parts[] = 'SameSite=' . $parameters['samesite'];

        return implode('; ', $parts);
    }

    /**
     * Builds the reusable application graph once per app instance.
     *
     * This covers module, provider, declaration, and controller metadata. Request-bound
     * state is still refreshed for every request cycle in {@see refreshRequestScope()}.
     *
     * @return void
     * @throws EntryNotFoundException
     * @throws HttpException
     * @throws ContainerException
     * @throws ReflectionException
     */
    protected function prepareApplicationGraph(): void
    {
        if ($this->applicationGraphPrepared) {
            return;
        }

        $this->resolveModules();
        $this->moduleManager->configureInjectorExtensions();
        $this->resolveProviders();
        $this->resolveDeclarations();
        $this->resolveControllers();
        $this->invokeModuleInitHooks();
        $this->invokeApplicationBootstrapHooks();
        $this->applicationGraphPrepared = true;
        $this->applicationGraphBuildCount++;
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
     * @return ReflectionAttribute[]
     */
    private function getModuleTokens(): array
    {
        return $this->moduleManager->getModuleTokens();
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
     * @return string[]
     */
    private function getProviderTokens(): array
    {
        return $this->moduleManager->getProviderTokens();
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
     * Invokes module initialization hooks once the reusable app graph is ready.
     *
     * @return void
     * @throws ContainerException
     * @throws ReflectionException
     */
    protected function invokeModuleInitHooks(): void
    {
        if ($this->moduleInitInvoked) {
            return;
        }

        foreach ($this->resolveLifecycleTargets() as $target) {
            if ($target instanceof OnModuleInitInterface) {
                $target->onModuleInit();
            }
        }

        $this->moduleInitInvoked = true;
    }

    /**
     * Resolves the default-scoped modules and providers that participate in app bootstrap.
     *
     * @return array<int, object>
     * @throws ContainerException
     * @throws ReflectionException
     */
    protected function resolveLifecycleTargets(): array
    {
        if (is_array($this->lifecycleTargets)) {
            return $this->lifecycleTargets;
        }

        $instances = [];
        $seen = [];

        foreach (array_keys($this->moduleManager->getModuleTokens()) as $moduleClass) {
            $instance = $this->instantiateLifecycleModule($moduleClass);
            $key = $moduleClass . '#' . spl_object_id($instance);

            if (!isset($seen[$key])) {
                $instances[] = $instance;
                $seen[$key] = true;
            }
        }

        foreach (array_keys($this->moduleManager->getProviderTokens()) as $providerClass) {
            if (!$this->shouldParticipateInBootstrap($providerClass)) {
                continue;
            }

            $instance = $this->injector->resolve($providerClass);

            if (!is_object($instance)) {
                continue;
            }

            $key = $providerClass . '#' . spl_object_id($instance);

            if (!isset($seen[$key])) {
                $instances[] = $instance;
                $seen[$key] = true;
            }
        }

        return $this->lifecycleTargets = $instances;
    }

    /**
     * Resolves a module instance using the same dependency rules as middleware configuration.
     *
     * @param class-string $moduleClass
     * @return object
     * @throws ContainerException
     * @throws ReflectionException
     */
    protected function instantiateLifecycleModule(string $moduleClass): object
    {
        $reflectionClass = new ReflectionClass($moduleClass);
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor || !$constructor->getParameters()) {
            return $reflectionClass->newInstance();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type || $type instanceof \ReflectionUnionType || $type->isBuiltin()) {
                $dependencies[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                continue;
            }

            $dependencies[] = $this->injector->resolve($type->getName());
        }

        return $reflectionClass->newInstanceArgs($dependencies);
    }

    /**
     * Determines whether the given provider class should participate in bootstrap hooks.
     *
     * Request-scoped and transient providers stay out of application bootstrap because they
     * are not safe to pin into the long-lived application graph.
     *
     * @param class-string $providerClass
     * @return bool
     * @throws ReflectionException
     */
    protected function shouldParticipateInBootstrap(string $providerClass): bool
    {
        $reflectionClass = new ReflectionClass($providerClass);

        return $this->injector->getDependencyScope($providerClass, $reflectionClass) === \Assegai\Core\Enumerations\Scope::DEFAULT;
    }

    /**
     * Invokes application bootstrap hooks once the reusable app graph is ready.
     *
     * @return void
     * @throws ContainerException
     * @throws ReflectionException
     */
    protected function invokeApplicationBootstrapHooks(): void
    {
        if ($this->applicationBootstrapInvoked) {
            return;
        }

        foreach ($this->resolveLifecycleTargets() as $target) {
            if ($target instanceof OnApplicationBootstrapInterface) {
                $target->onApplicationBootstrap();
            }
        }

        $this->applicationBootstrapInvoked = true;
    }

    /**
     * Invokes application shutdown hooks once when the active runtime is shutting down.
     *
     * @return void
     * @throws ContainerException
     * @throws ReflectionException
     */
    protected function invokeApplicationShutdownHooks(): void
    {
        if ($this->applicationShutdownInvoked) {
            return;
        }

        foreach ($this->resolveLifecycleTargets() as $target) {
            if ($target instanceof OnApplicationShutdownInterface) {
                $target->onApplicationShutdown();
            }
        }

        $this->applicationShutdownInvoked = true;
    }

    /**
     * Serves the generated API docs endpoints when requested.
     *
     * @return bool
     * @throws EntryNotFoundException
     */
    protected function handleGeneratedApiDocsRequest(): bool
    {
        $requestPath = trim($this->request->getPath(), '/');

        if ($this->projectConfig?->get('apiDocs.enabled', false) !== true) {
            return false;
        }

        if (!in_array($requestPath, ['docs', 'openapi.json'], true)) {
            return false;
        }

        $response = $this->response;
        $response->reset();
        $this->router->runMiddleware($this->request, $response, function () use ($requestPath, $response): void {
            $document = $this->describeApi();

            if ($requestPath === 'openapi.json') {
                $response->jsonRaw($document);
                return;
            }

            $renderer = new SwaggerUiRenderer();
            $response->setContentType(ContentType::HTML);
            $response->setBody(
                $renderer->render(
                    specUrl: '/openapi.json',
                    title: ($document['info']['title'] ?? 'Assegai API') . ' Docs',
                )
            );
        });

        $this->closeSessionForCurrentRequest();
        $this->responder->respond($response);
        return true;
    }

    /**
     * Builds the generated OpenAPI document for the current application graph.
     *
     * @return array<string, mixed>
     */
    public function describeApi(): array
    {
        $this->prepareApplicationGraph();

        $generator = new OpenApiGenerator(
            $this->controllerManager,
            $this->moduleManager,
            $this->request,
            $this->composerConfig,
            $this->projectConfig,
        );

        return $generator->generate($this->rootModuleClass);
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
        $this->closeSessionForCurrentRequest();
        $this->responder->respond(response: $this->response);
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

    /**
     * Builds module-configured middleware once the controller graph is available.
     *
     * @return void
     * @throws ContainerException
     * @throws ReflectionException
     */
    protected function prepareMiddlewareGraph(): void
    {
        if ($this->middlewarePrepared) {
            return;
        }

        $this->resolveMiddleware();
        $this->middlewarePrepared = true;
        $this->middlewareBuildCount++;
    }

    /**
     * Resolves module-configured middleware and shares the resulting consumer with the router.
     *
     * @return void
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function resolveMiddleware(): void
    {
        $consumer = new MiddlewareConsumer();

        if ($this->middlewareConsumer) {
            $consumer->merge($this->middlewareConsumer);
        }

        $this->moduleManager->configureMiddleware($consumer);
        $this->middlewareConsumer = $consumer;
        $this->router->setMiddlewareConsumer($consumer);
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
     * Ensures module and controller metadata exists before API inspection work runs.
     *
     * @return void
     */
    protected function ensureControllerGraphResolved(): void
    {
        $this->prepareApplicationGraph();
    }
}
