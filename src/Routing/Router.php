<?php

namespace Assegai\Core\Routing;

use Assegai\Core\Attributes\Controller;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Head;
use Assegai\Core\Attributes\Http\Options;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Put;
use Assegai\Core\Attributes\UseGuards;
use Assegai\Core\Attributes\UseInterceptors;
use Assegai\Core\Consumers\GuardsConsumer;
use Assegai\Core\Enumerations\Http\RequestMethod;
use Assegai\Core\Exceptions\Container\ContainerException;
use Assegai\Core\Exceptions\Container\EntryNotFoundException;
use Assegai\Core\Exceptions\Http\ForbiddenException;
use Assegai\Core\Exceptions\Http\HttpException;
use Assegai\Core\Exceptions\Http\NotFoundException;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\Injector;
use Assegai\Core\Interceptors\InterceptorsConsumer;
use Assegai\Core\Interfaces\IOnGuard;
use Assegai\Core\Util\Validator;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use stdClass;

final class Router
{
  private static ?Router $instance = null;
  private Injector $injector;
  private GuardsConsumer $guardsConsumer;
  private InterceptorsConsumer $interceptorsConsumer;

  private final function __construct()
  {
    $this->injector = Injector::getInstance();
    $this->interceptorsConsumer = InterceptorsConsumer::getInstance();
    $this->guardsConsumer = GuardsConsumer::getInstance();
  }

  /**
   * @return Router
   */
  public static function getInstance(): Router
  {
    if (!self::$instance)
    {
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
   * @param Request $request
   * @param ReflectionClass[] $controllerTokensList
   * @return object
   * @throws ContainerException
   * @throws NotFoundException
   * @throws ReflectionException
   * @throws HttpException
   */
  public function getActivatedController(Request $request, array $controllerTokensList): object
  {
    $activatedController = null;

    foreach ($controllerTokensList as $reflectionController)
    {
      if ($this->canActivateController($reflectionController))
      {
        return $this->activateController($reflectionController);
      }
    }

    if (!$activatedController)
    {
      throw new NotFoundException(path: $request->getPath());
    }

    return $activatedController;
  }

  /**
   * @param ReflectionClass $reflectionController
   * @return bool
   * @throws HttpException
   */
  private function canActivateController(ReflectionClass $reflectionController): bool
  {
    $request = Request::getInstance();
    $path = str_starts_with($request->getPath(), '/') ? $request->getPath() : '/' . $request->getPath();

    $attributes = $reflectionController->getAttributes(Controller::class);

    if (empty($attributes))
    {
      throw new HttpException("Invalid controller: " . $reflectionController->getName());
    }

    foreach ($attributes as $attribute)
    {
      $instance = $attribute->newInstance();
      $prefix = str_replace('/^\/\//', '', '/' . $instance->path);

      if ($path === $prefix)
      {
        return true;
      }

      if (str_starts_with($path, $prefix))
      {
        if (!empty($path) && $prefix === '/')
        {
          continue;
        }
        return true;
      }
    }

    $method = trim($path, '/');
    return $reflectionController->hasMethod($method);
  }

  /**
   * @param ReflectionClass $reflectionController
   * @return object Returns an instance of the activated controller
   * @throws ReflectionException
   */
  private function activateController(ReflectionClass $reflectionController): object
  {
    $dependencies = [];

    if ($constructor = $reflectionController->getConstructor())
    {
      $constructorParams = $constructor->getParameters();

      # Instantiate attributes
      $controllerReflectionAttributes = $reflectionController->getAttributes();
      $controllerAttributes = [];

      foreach ($controllerReflectionAttributes as $controllerAttribute)
      {
        $controllerAttributes[] = $controllerAttribute->newInstance();
      }

      foreach ($constructorParams as $param) {
        try {
          $dependencies[] = $this->injector->resolve($param->getType()->getName());
        }
        catch (Exception $exception)
        {
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

    foreach($reflectionMethods as $reflectionMethod)
    {
      if ($this->isValidHandler($reflectionMethod))
      {
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
    foreach ($handlers as $handler)
    {
      if ($this->canActivateHandler(handler: $handler, controller: $controller, request: $request))
      {
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
    foreach ($attributes as $attribute)
    {
      if (Validator::isValidRequestMapperAttribute($attribute))
      {
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

    if (empty($attributes))
    {
      return false;
    }

    $requestMapperClassFound = false;
    $foundPathMatch = false;

    foreach ($attributes as $attribute)
    {
      $foundPathMatch = $this->isPathMatch(pattern: $pattern, path: $path);

      if ($foundPathMatch === false && $handler->getShortName() === trim($request->getPath(), '/'))
      {
        $foundPathMatch = true;
      }

      if ($foundPathMatch)
      {
        switch($request->getMethod())
        {
          case RequestMethod::OPTIONS:
            if ($attribute->getName() === Options::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::GET:
            if ($attribute->getName() === Get::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::POST:
            if ($attribute->getName() === Post::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::PUT:
            if ($attribute->getName() === Put::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::PATCH:
            if ($attribute->getName() === Patch::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::DELETE:
            if ($attribute->getName() === Delete::class)
            {
              $requestMapperClassFound = true;
            }
            break;

          case RequestMethod::HEAD:
            if ($attribute->getName() === Head::class)
            {
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

    if (!$activatedHandler)
    {
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

    if ($useGuardsAttributes)
    {
      /** @var UseGuards $handlerUseGuardsAttribute */
      $handlerUseGuardsAttribute = $useGuardsAttributes[0]->newInstance();

      if (! $this->guardsConsumer->canActivate(guards: $handlerUseGuardsAttribute->guards, context: $context) )
      {
        throw new ForbiddenException();
      }
    }

    # Consume handler interceptors
    $handlerInterceptorCallHandlers = [];
    $useInterceptorsAttributes = $activatedHandler->getAttributes(UseInterceptors::class);

    if ($useInterceptorsAttributes)
    {
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

    try
    {
      $result = $activatedHandler->invokeArgs($controller, $dependencies);
    }
    catch (Exception $e)
    {
      throw new HttpException(message: $e->getMessage());
    }

    if ($result instanceof Response)
    {
      return $result;
    }

    if (is_null($result))
    {
      $result = [];
    }
    $context->switchToHttp()->getResponse()->setBody($result);

    # Run handler Interceptors
    /** @var callable $handler */
    foreach ($handlerInterceptorCallHandlers as $handler)
    {
      /** @var ExecutionContext $context */
      $context = $handler($context);
    }

    # Run controller Interceptors
    /** @var callable $handler */
    foreach ($controllerInterceptorCallHandlers as $handler)
    {
      /** @var ExecutionContext $context */
      $context = $handler($context);
    }

    return $context?->switchToHttp()->getResponse() ?? Response::getInstance();
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
    foreach ($attributes as $attribute)
    {
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
    foreach ($attributes as $attribute)
    {
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
    if (str_ends_with($path, '/'))
    {
      $path = rtrim($path, '/');
    }

    // Replace `*` with `.+` to match any character one or more times
    $path = str_replace('*', '.+', $path);

    // Replace named placeholders with regex pattern to match any word characters one or more times
    return preg_replace(pattern: '/(\/?):\w+/', replacement: '$1([\w-]+)', subject: $path);
  }

  /**
   * @param string $pattern
   * @param string $path
   * @return bool
   */
  private function isPathMatch(string $pattern, string $path): bool
  {
    $path = preg_replace('/^\//', '', $path);
    $pattern = str_replace('/', '\/', $pattern);
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
    foreach ($reflectionAttributes as $attribute)
    {
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
    if ($useGuardsAttributes)
    {
      /** @var UseGuards $controllerUseGuardsInstance */
      $controllerUseGuardsInstance = $useGuardsAttributes[0]->newInstance();

      if (! $this->guardsConsumer->canActivate(guards: $controllerUseGuardsInstance->guards, context: $context) )
      {
        if ($controller instanceof IOnGuard)
        {
          $controller->onGuard(context: $context);
        }
        else
        {
          throw new ForbiddenException();
        }
      }
    }
  }

  /**
   *
   *
   * @param ReflectionClass $controllerReflection
   * @param object $controller
   * @param ExecutionContext $context
   * @return InterceptorsConsumer[]
   */
  private function consumeControllerInterceptors(
    ReflectionClass $controllerReflection,
    ExecutionContext $context
  ): array
  {
    $controllerInterceptorCallHandlers = [];
    $useInterceptorsAttributes = $controllerReflection->getAttributes(UseInterceptors::class);

    if ($useInterceptorsAttributes)
    {
      /** @var UseInterceptors $controllerUseInterceptorsInstance */
      $controllerUseInterceptorsInstance = $useInterceptorsAttributes[0]->newInstance();

      $controllerInterceptorCallHandlers =
        $this->interceptorsConsumer
          ->intercept(
            interceptors: $controllerUseInterceptorsInstance->interceptorsList,
            context: $context
          );
    }

    return $controllerInterceptorCallHandlers;
  }

  /**
   * Resolves the dependencies for the given handler.
   *
   * @param ReflectionMethod $activatedHandler
   * @param Request $request
   * @return array
   * @throws ContainerException
   * @throws EntryNotFoundException
   * @throws ReflectionException
   */
  private function resolveHandlerParameters(ReflectionMethod $activatedHandler, Request $request): array
  {
    $dependencies = [];

    $params = $activatedHandler->getParameters();
    foreach ($params as $param)
    {
      $paramTypeName = $param->getType()?->getName() ?? 'stdClass';
      $isStandardClassType = is_subclass_of($paramTypeName, stdClass::class) || $paramTypeName === 'stdClass';

      $dependencies[] = match(true) {
        $param->getType()?->isBuiltin(),
        $isStandardClassType => $this->injector->resolveBuiltIn($param, $request),
        default => $this->injector->resolve($paramTypeName)
      };
    }

    return $dependencies;
  }
}