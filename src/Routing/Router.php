<?php

namespace Assegai\Core\Routing;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Head;
use Assegai\Core\Attributes\Http\Options;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Put;
use Assegai\Core\Attributes\Http\Query;
use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\Req;
use Assegai\Core\Attributes\Res;
use Assegai\Core\Attributes\UseGuards;
use Assegai\Core\Attributes\UseInterceptors;
use Assegai\Core\Consumers\GuardsConsumer;
use Assegai\Core\ControllerManager;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\ForbiddenException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\Exceptions\InterceptorException;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Interceptors\InterceptorsConsumer;
use Assegai\Core\Interfaces\IOnGuard;
use Assegai\Core\Interfaces\IPipeTransform;
use Assegai\Core\Util\TypeManager;
use Assegai\Core\Util\Validator;
use Error;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionUnionType;
use stdClass;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * The Router class is responsible for routing incoming requests to the appropriate controller and handler.
 *
 * @package Assegai\Core\Routing
 */
final class Router
{
  /**
   * @var Router|null The Router instance.
   */
  private static ?Router $instance = null;
  /**
   * @var Injector The injector instance.
   */
  private Injector $injector;
  /**
   * @var GuardsConsumer The guards consumer instance.
   */
  private GuardsConsumer $guardsConsumer;
  /**
   * @var InterceptorsConsumer The interceptors consumer instance.
   */
  private InterceptorsConsumer $interceptorsConsumer;
  /**
   * @var ControllerManager The controller manager instance.
   */
  private ControllerManager $controllerManager;
  /**
   * @var array The global pipes.
   */
  private array $globalPipes = [];
  /**
   * @var array The global interceptors.
   */
  private array $globalInterceptors = [];

  private final function __construct()
  {
    $this->injector = Injector::getInstance();
    $this->interceptorsConsumer = InterceptorsConsumer::getInstance();
    $this->guardsConsumer = GuardsConsumer::getInstance();
    $this->controllerManager = ControllerManager::getInstance();
  }

  /**
   * @return Router
   */
  public static function getInstance(): Router
  {
    if (!self::$instance) {
      self::$instance = new Router();
    }

    return self::$instance;
  }

  /**
   * @return Request
   */
  public function getRequest(): Request
  {
    return Request::getInstance();
  }

  /**
   * Determines the controller to be activated based on the given request.
   *
   * @param Request $request The request to be processed.
   * @param ReflectionClass[] $controllerTokensList A list of controller reflection instances.
   * @return object The activated controller.
   * @throws ContainerException If there was an error during dependency injection.
   * @throws NotFoundException
   * @throws ReflectionException
   * @throws HttpException
   */
  public function getActivatedController(Request $request, array $controllerTokensList): object
  {
    $activatedController = null;

    foreach ($controllerTokensList as $reflectionController) {
      if ($this->isRootController($reflectionController)) {
        $activatedController = $this->activateController($reflectionController);
        continue;
      }

      if ($this->canActivateController($reflectionController)) {
        return $this->activateController($reflectionController);
      }
    }

    if (is_null($activatedController)) {
      throw new NotFoundException(path: $request->getPath());
    }

    return $activatedController;
  }

  /**
   * Determines if the given controller can be activated.
   *
   * @param ReflectionClass $reflectionController The reflection instance of the controller to be activated.
   * @return bool True if the controller can be activated, false otherwise.
   * @throws HttpException If the controller is invalid.
   * @throws ReflectionException If there was an error processing a reflection.
   */
  private function canActivateController(ReflectionClass $reflectionController): bool
  {
    $request = Request::getInstance();
    $path = str_starts_with($request->getPath(), '/') ? $request->getPath() : '/' . $request->getPath();

    $controllerClassAttributes = $reflectionController->getAttributes(Controller::class);

    if (empty($controllerClassAttributes)) {
      throw new HttpException("Invalid controller: " . $reflectionController->getName());
    }

    foreach ($controllerClassAttributes as $attribute) {
      $instance = $attribute->newInstance();
      $prefix = str_replace('/^\/\//', '', '/' . $instance->path);

      if ($path === $prefix) {
        return true;
      }

      if (str_starts_with($path, $prefix)) {
        if (!empty($path) && $prefix === '/') {
          continue;
        }
        return true;
      }
    }

    if ($this->isRootController($reflectionController)) {
      return true;
    }

    return false;
  }

  /**
   * Activates the given controller.
   *
   * @param ReflectionClass $reflectionController The reflection instance of the controller to be activated.
   * @return object Returns an instance of the activated controller
   * @throws ReflectionException
   */
  private function activateController(ReflectionClass $reflectionController): object
  {
    $dependencies = [];

    if ($constructor = $reflectionController->getConstructor()) {
      $constructorParams = $constructor->getParameters();

      # Instantiate attributes
      $controllerReflectionAttributes = $reflectionController->getAttributes();
      $controllerAttributes = [];

      foreach ($controllerReflectionAttributes as $controllerAttribute) {
        $controllerAttributes[] = $controllerAttribute->newInstance();
      }

      foreach ($constructorParams as $param) {
        try {
          $dependencies[] = $this->injector->resolve($param->getType()->getName());
        } catch (Exception $exception) {
          exit(var_export([
            'exception' => $exception,
            'controllerAttributes' => $controllerAttributes,
            'dependencies' => $dependencies,
            'param' => $param,
            'param-type' => $param->getType()->getName(),
          ], true));
        }
      }
    }

    return $reflectionController->newInstanceArgs($dependencies);
  }

  /**
   * @param object $controller
   * @return ReflectionMethod[] Returns a list of handlers belonging to the given controller
   */
  public function getControllerHandlers(object $controller): array
  {
    $handlers = [];
    $reflectionClass = new ReflectionClass($controller);
    $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach($reflectionMethods as $reflectionMethod) {
      if ($this->isValidHandler($reflectionMethod)) {
        $handlers[] = $reflectionMethod;
      }
    }

    return $handlers;
  }

  /**
   * @param ReflectionMethod[] $handlers
   * @param object $controller
   * @param Request $request
   * @return ReflectionMethod|null
   */
  public function getActivatedHandler(array $handlers, object $controller, Request $request): ?ReflectionMethod
  {
    foreach ($handlers as $handler) {
      if ($this->canActivateHandler(handler: $handler, controller: $controller, request: $request)) {
        $this->parseHandlerAttributes($handler);
        return $handler;
      }
    }

    return null;
  }

  /**
   * @param ReflectionMethod $method
   * @return bool
   */
  public function isValidHandler(ReflectionMethod $method): bool
  {
    $attributes = $method->getAttributes();

    $foundRequestMapperAttribute = false;
    foreach ($attributes as $attribute) {
      if (Validator::isValidRequestMapperAttribute($attribute)) {
        $foundRequestMapperAttribute = true;
      }
    }

    return $foundRequestMapperAttribute;
  }

  /**
   * @param ReflectionMethod $handler
   * @param object $controller
   * @param Request $request
   * @return bool
   */
  public function canActivateHandler(ReflectionMethod $handler, object $controller, Request $request): bool
  {
    $path = $request->getPath();
    $controllerPrefix = $this->getControllerPrefix(controller: $controller);
    $handlerPath = $this->getHandlerPath(handler: $handler);

    $pattern = $this->getPathMatchingPattern(path: "$controllerPrefix/$handlerPath");

    $request->extractParams(path: $path, pattern: $pattern);

    $attributes = $handler->getAttributes();

    if (empty($attributes)) {
      return false;
    }

    $requestMapperClassFound = false;
    $foundPathMatch = false;

    foreach ($attributes as $attribute) {
      $foundPathMatch = $this->patternMatchesPath(pattern: $pattern, path: $path);

      if ($foundPathMatch === false && $handler->getShortName() === trim($request->getPath(), '/')) {
        $foundPathMatch = true;
      }

      if ($foundPathMatch) {
        switch($request->getMethod()) {
          case RequestMethod::OPTIONS:
            if ($attribute->getName() === Options::class) {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::GET:
            if ($attribute->getName() === Get::class) {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::POST:
            if ($attribute->getName() === Post::class) {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::PUT:
            if ($attribute->getName() === Put::class) {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::PATCH:
            if ($attribute->getName() === Patch::class) {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::DELETE:
            if ($attribute->getName() === Delete::class) {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::HEAD:
            if ($attribute->getName() === Head::class) {
              $requestMapperClassFound = true;
            }
        }
      }
    }

    return $foundPathMatch && $requestMapperClassFound;
  }

  /**
   * Handles an incoming client request using the given controller.
   *
   * @param Request $request The request to be processed.
   * @param object $controller The controller to handle the request.
   * @return Response The response to be sent back to the client.
   * @throws ContainerException If there was an error during dependency injection.
   * @throws EntryNotFoundException If a dependency was not found in the DI container.
   * @throws ForbiddenException If the request is forbidden.
   * @throws HttpException If there was an error processing the request.
   * @throws NotFoundException If the requested resource could not be found.
   * @throws ReflectionException If there was an error processing a reflection.
   */
  public function handleRequest(Request $request, object $controller): Response
  {
    $handlers = $this->getControllerHandlers(controller: $controller);
    $activatedHandler = $this->getActivatedHandler(handlers: $handlers, controller: $controller, request: $request);

    if (!$activatedHandler) {
      throw new NotFoundException($request->getPath());
    }

    $controllerReflection = new ReflectionClass($controller);
    $context = $this->createContext(class: $controllerReflection, handler: $activatedHandler);

    # Consume controller guards
    $this->consumeControllerGuards($controllerReflection, $controller, $context);

    # Consume controller interceptors
    $controllerInterceptorCallHandlers = $this->consumeControllerInterceptors($controllerReflection, $context);

    # Consume handler guards
    $useGuardsAttributes = $activatedHandler->getAttributes(UseGuards::class);

    if ($useGuardsAttributes) {
      /** @var UseGuards $handlerUseGuardsAttribute */
      $handlerUseGuardsAttribute = $useGuardsAttributes[0]->newInstance();

      if (! $this->guardsConsumer->canActivate(guards: $handlerUseGuardsAttribute->guards, context: $context) ) {
        throw new ForbiddenException();
      }
    }

    # Consume handler interceptors
    $handlerInterceptorCallHandlers = [];
    $useInterceptorsAttributes = $activatedHandler->getAttributes(UseInterceptors::class);

    if ($useInterceptorsAttributes) {
      /** @var UseInterceptors $handlerUseInterceptorsInstance */
      $handlerUseInterceptorsInstance = $useInterceptorsAttributes[0]->newInstance();

      $handlerInterceptorCallHandlers =
        $this->interceptorsConsumer
          ->intercept(
            interceptors: $handlerUseInterceptorsInstance->interceptorsList,
            context: $context
          );
    }

    # Resolve handler parameters
    $dependencies = $this->resolveHandlerParameters($activatedHandler, $request);

    $result = $activatedHandler->invokeArgs($controller, $dependencies);

    if ($result instanceof Response) {
      return $result;
    }

    if (is_null($result)) {
      $result = [];
    }
    $context->switchToHttp()->getResponse()->setBody($result);

    # Run handler Interceptors
    /** @var callable $handler */
    foreach ($handlerInterceptorCallHandlers as $handler) {
      /** @var ExecutionContext $context */
      $context = $handler($context);
    }

    # Run controller Interceptors
    /** @var callable $handler */
    foreach ($controllerInterceptorCallHandlers as $handler) {
      /** @var ExecutionContext $context */
      $context = $handler($context);
    }

    return match(true) {
      $context instanceof ExecutionContext => $context->switchToHttp()->getResponse(),
      $context instanceof Response => $context,
      default => Response::getInstance()
    };
  }

  /**
   * Adds global pipes to the router.
   *
   * @param array $pipes The list of global pipes to be added.
   * @return void
   */
  public function addGlobalPipes(array $pipes): void
  {
    $this->globalPipes = [...$this->globalPipes, ...$pipes];
  }

  /**
   * Adds global interceptors to the router.
   *
   * @param array $interceptors The list of global interceptors to be added.
   * @return void
   */
  public function addGlobalInterceptors(array $interceptors): void
  {
    $this->globalInterceptors = [...$this->globalInterceptors, ...$interceptors];
  }

  /**
   * Redirects the client to the given URL.
   *
   * @param string $url The URL to redirect the client to.
   * @param int|null $statusCode The status code to be used for the redirect.
   * @return never
   * @throws HttpException If the HTTP status code could not be set.
   */
  public static function redirectTo(string $url, ?int $statusCode = null): never
  {
    if ($statusCode) {
      $code = $statusCode;
      if (false === http_response_code($code)) {
        throw new HttpException("Failed to set HTTP status code to $code");
      }
    }
    header("Content-Type: text/html");
    exit(<<<HTML
      <script>
        window.location.href = "$url";
</script>
HTML);
  }

  /**
   * Returns the path prefix for the given controller.
   * @param object $controller The controller to get the path prefix for.
   * @return string The path prefix for the given controller.
   */
  private function getControllerPrefix(object $controller): string
  {
    $reflectionController = new ReflectionClass($controller);
    $attributes = $reflectionController->getAttributes(Controller::class);

    # Find a Controller attribute
    foreach ($attributes as $attribute) {
      $instance = $attribute->newInstance();
      return $instance->path;
    }

    # If no attributes are found, return an empty string.
    return '';
  }

  /**
   * @param ReflectionMethod $handler
   * @return string
   */
  private function getHandlerPath(ReflectionMethod $handler): string
  {
    # TODO: Filter by Request class
    /*
     * There is need to create a Request base class from which all HTTP verb methods inherit
     */
    $attributes = $handler->getAttributes();
    foreach ($attributes as $attribute) {
      return $attribute->newInstance()->path;
    }

    return '';
  }

  /**
   * Returns the regular expression pattern for matching the given path.
   *
   * @param string $path The path to be matched.
   * @return string The regular expression pattern for matching the given path.
   */
  private function getPathMatchingPattern(string $path): string
  {
    // Remove trailing slash if it exists
    if (str_ends_with($path, '/')) {
      $path = rtrim($path, '/');
    }

    // Replace `*` with `.+` to match any character one or more times
    $path = str_replace('*', '.+', $path);

    // Replace named placeholders with regex pattern to match any word characters one or more times
    return preg_replace(pattern: '/(\/?):\w+/', replacement: '$1([\w-]+)', subject: $path);
  }

  /**
   * Determines if the given pattern matches the given path.
   *
   * @param string $pattern The pattern to be matched.
   * @param string $path The path to be matched.
   * @return bool True if the pattern matches the path, false otherwise.
   */
  private function patternMatchesPath(string $pattern, string $path): bool
  {
    $path = preg_replace('/^\//', '', $path);
    $pattern = str_replace('/', '\/?', $pattern);
    $result = preg_match("/^$pattern\/?$/", $path);

    return boolval($result) === true;
  }

  /**
   * @param ReflectionMethod $activatedHandler A reflection instance of the handler method to be parsed.
   * @return void
   */
  private function parseHandlerAttributes(ReflectionMethod $activatedHandler): void
  {
    $reflectionAttributes = $activatedHandler->getAttributes();
    foreach ($reflectionAttributes as $attribute) {
      $attribute->newInstance();
    }
  }

  /**
   * @param ReflectionClass $class
   * @param ReflectionMethod $handler
   * @return ExecutionContext
   */
  private function createContext(ReflectionClass $class, ReflectionMethod $handler): ExecutionContext
  {
    return new ExecutionContext(class: $class, handler: $handler);
  }

  /**
   * Consumes the guards for the given controller.
   *
   * @param ReflectionClass $controllerReflection The reflection instance of the controller.
   * @param object $controller The controller instance.
   * @param ExecutionContext $context The execution context.
   * @return void
   * @throws ForbiddenException If the request is forbidden.
   */
  private function consumeControllerGuards(ReflectionClass $controllerReflection, object $controller, ExecutionContext $context): void
  {
    $useGuardsAttributes = $controllerReflection->getAttributes(UseGuards::class);
    if ($useGuardsAttributes) {
      /** @var UseGuards $controllerUseGuardsInstance */
      $controllerUseGuardsInstance = $useGuardsAttributes[0]->newInstance();

      if (! $this->guardsConsumer->canActivate(guards: $controllerUseGuardsInstance->guards, context: $context) ) {
        if ($controller instanceof IOnGuard) {
          $controller->onGuard(context: $context);
        } else {
          throw new ForbiddenException();
        }
      }
    }
  }

  /**
   * Consumes the interceptors for the given controller.
   *
   * @param ReflectionClass $controllerReflection The reflection instance of the controller.
   * @param ExecutionContext $context The execution context.
   * @return InterceptorsConsumer[] Returns a list of interceptor call handlers.
   * @throws ReflectionException
   * @throws InterceptorException
   */
  private function consumeControllerInterceptors(
    ReflectionClass $controllerReflection,
    ExecutionContext $context
  ): array
  {
    $controllerInterceptorCallHandlers = [];

    if ($this->globalInterceptors) {
      $useInterceptorInstance = new UseInterceptors(interceptors: $this->globalInterceptors);

      $controllerInterceptorCallHandlers = $this->interceptorsConsumer->intercept(
        interceptors: $useInterceptorInstance->interceptorsList,
        context: $context
      );
    }

    $useInterceptorsAttributes = $controllerReflection->getAttributes(UseInterceptors::class);

    if ($useInterceptorsAttributes) {
      /** @var UseInterceptors $controllerUseInterceptorsInstance */
      $controllerUseInterceptorsInstance = $useInterceptorsAttributes[0]->newInstance();

      $controllerInterceptorCallHandlers = [...$controllerInterceptorCallHandlers,
        ...$this->interceptorsConsumer
          ->intercept(
            interceptors: $controllerUseInterceptorsInstance->interceptorsList,
            context: $context
          )
      ];
    }

    return $controllerInterceptorCallHandlers;
  }

  /**
   * Resolves the dependencies for the given handler.
   *
   * @param ReflectionMethod $activatedHandler The reflection instance of the handler method.
   * @param Request $request The request to be processed.
   * @return array<int|string, mixed> The resolved dependencies for the handler.
   * @throws ContainerException If there was an error during dependency injection.
   * @throws EntryNotFoundException If a dependency was not found in the DI container.
   * @throws ReflectionException If there was an error processing a reflection.
   */
  private function resolveHandlerParameters(ReflectionMethod $activatedHandler, Request $request): array
  {
    $dependencies = [];

    $params = $activatedHandler->getParameters();

    foreach ($params as $param) {
      $paramIsUnionType = $param->getType() instanceof ReflectionUnionType;
      $paramAttributeReflections = $param->getAttributes();

      $paramTypeName = match (true) {
        $paramIsUnionType => $param->getType()->getTypes()[0]->getName(),
        !is_null($param->getType()) => $param->getType()->getName(),
        default => 'stdClass'
      };
      $isStandardClassType = is_subclass_of($paramTypeName, stdClass::class) || $paramTypeName === 'stdClass';

      $dependency = match(true) {
        $paramIsUnionType => match(true) {
          $param->getType()->getTypes()[0]->isBuiltin(),
          $param->getType()->getTypes()[1]->isBuiltin() => $this->injector->resolveBuiltIn($param, $request, true),
        },
        $param->getType()?->isBuiltin(),
        $isStandardClassType => $this->injector->resolveBuiltIn($param, $request),
        default => $this->injector->resolve($paramTypeName, $paramAttributeReflections)
      };

      $dependencies[] = $this->bindRequestHandlerAttributes($param, $dependency, $request);
    }

    return $dependencies;
  }

  /**
   * Determines if the given controller is the root controller.
   *
   * @param ReflectionClass $controllerReflection The reflection instance of the controller.
   * @return bool True if the given controller is the root controller, false otherwise.
   * @throws ReflectionException If there was an error processing a reflection.
   */
  private function isRootController(ReflectionClass $controllerReflection): bool
  {
    return $controllerReflection->getName() === $this->controllerManager->getRootControllerClass();
  }

  /**
   * Binds the request handler attributes to the given parameter.
   *
   * @param ReflectionParameter $param
   * @param mixed $dependency
   * @param Request $request
   * @return mixed
   * @throws EntryNotFoundException
   * @throws HttpException
   * @throws ReflectionException
   */
  private function bindRequestHandlerAttributes(ReflectionParameter $param, mixed $dependency, Request $request): mixed
  {
    $paramAttributes = $param->getAttributes();
    $paramTypeName = $param->getType()->getName();

    foreach ($paramAttributes as $attribute) {
      $paramAttributeArgs = $attribute->getArguments();
      $paramAttributeInstance = $attribute->newInstance();

      switch ($paramAttributeInstance::class) {
        case Param::class:
          if (empty($paramAttributeArgs)) {
            return ($paramTypeName === 'string')
              ? json_encode($request->getParams())
              : (object)$request->getParams();
          }
          return $request->getParams()[$param->getPosition()] ??
            ($param->isOptional() ? $param->getDefaultValue() : null);

        case Query::class:;
          if (empty($paramAttributeArgs)) {
            return ($paramTypeName === 'string')
              ? json_encode($request->getQuery())
              : (object)$request->getQuery();
          }

          return $request->getQuery()->toArray()[$param->getName()] ??
            ($param->isOptional() ? $param->getDefaultValue() : null);

        case Body::class:
          $output = new ConsoleOutput();
          $output->setDecorated(true);

          if (empty($paramAttributeArgs)) {
            $body = ($paramTypeName === 'string')
              ? json_encode($request->getBody())
              : $request->getBody();
          } else {
            $key = $param->getName();
            $body = $paramAttributeInstance->value ?? null;

            if ($paramAttributeInstance->pipes) {
              if (is_array($paramAttributeInstance->pipes)) {
                foreach ($paramAttributeInstance->pipes as $pipe) {
                  $body = $this->transformBody($pipe, $body);
                }
              } else {
                $body = $this->transformBody($paramAttributeInstance->pipes, $body);
              }
            }
          }

          return is_object($body) ? TypeManager::castObjectToUserType($body, $paramTypeName) : $body;

        case Req::class:
          return $request;

        case Res::class:
          return Response::getInstance();

        default:
          if (property_exists($paramAttributeInstance, 'value')) {
            return $paramAttributeInstance->value;
          }
//        case Request::class:
//          $dependency = $request;
//          break;
//        case Response::class:
//          $dependency = Response::getInstance();
//          break;
//        case Body::class:
//          $body = Request::getInstance()->getBody();
//          if ($body->value) {
//            $dependency = $body->value;
//          } else {
//            foreach ($body as $key => $value) {
//              if (property_exists($dependency, $key)) {
//                $dependency->$key = $value;
//              }
//            }
//          }
//          break;
      }
    }

    return $dependency;
  }

  /**
   * Transforms the given body using the given pipe.
   *
   * @param string|IPipeTransform $pipe The pipe to be used for transformation.
   * @param mixed $body The body to be transformed.
   * @param array|stdClass|null $metaData The body to be transformed.
   * @return mixed The transformed body.
   * @throws ContainerException
   * @throws ReflectionException
   */
  private function transformBody(string|IPipeTransform $pipe, mixed $body, array|stdClass|null $metaData = null): mixed
  {
    if (is_string($pipe)) {
      $pipe = $this->injector->resolve($pipe);
    }

    return $pipe->transform($body, $metaData);
  }
}