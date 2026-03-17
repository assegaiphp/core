<?php

namespace Assegai\Core\ApiDocs;

use Assegai\Core\Attributes\HostParam;
use Assegai\Core\Attributes\Http\Body;
use Assegai\Core\Attributes\Http\Delete;
use Assegai\Core\Attributes\Http\Get;
use Assegai\Core\Attributes\Http\Head;
use Assegai\Core\Attributes\Http\Header;
use Assegai\Core\Attributes\Http\HttpCode;
use Assegai\Core\Attributes\Http\Options;
use Assegai\Core\Attributes\Http\Patch;
use Assegai\Core\Attributes\Http\Post;
use Assegai\Core\Attributes\Http\Put;
use Assegai\Core\Attributes\Http\Query;
use Assegai\Core\Attributes\Http\Redirect;
use Assegai\Core\Attributes\Http\Sse;
use Assegai\Core\Attributes\Param;
use Assegai\Core\Attributes\ResponseStatus;
use Assegai\Core\Config\ComposerConfig;
use Assegai\Core\Config\ProjectConfig;
use Assegai\Core\ControllerManager;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Http\Responses\ApiResponse;
use Assegai\Core\Http\Responses\Response;
use Assegai\Core\ModuleManager;
use Assegai\Core\Rendering\View;
use Assegai\Core\Util\Validator;
use Assegai\Core\Components\Interfaces\ComponentInterface;
use Assegai\Validation\Attributes\IsAlpha;
use Assegai\Validation\Attributes\IsAlphanumeric;
use Assegai\Validation\Attributes\IsArray;
use Assegai\Validation\Attributes\IsAscii;
use Assegai\Validation\Attributes\IsBetween;
use Assegai\Validation\Attributes\IsDate;
use Assegai\Validation\Attributes\IsDomain;
use Assegai\Validation\Attributes\IsEmail;
use Assegai\Validation\Attributes\IsEnum;
use Assegai\Validation\Attributes\IsEqualTo;
use Assegai\Validation\Attributes\IsInt;
use Assegai\Validation\Attributes\IsNotEmpty;
use Assegai\Validation\Attributes\IsNumber;
use Assegai\Validation\Attributes\IsNumeric;
use Assegai\Validation\Attributes\IsOptional;
use Assegai\Validation\Attributes\IsString;
use Assegai\Validation\Attributes\IsUrl;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

class OpenApiGenerator
{
  /**
   * @var array<class-string, string>
   */
  private array $componentNames = [];

  /**
   * @var array<string, class-string>
   */
  private array $componentNameIndex = [];

  /**
   * @var array<string, array<string, mixed>>
   */
  private array $componentSchemas = [];

  /**
   * @var array<class-string, array<string, mixed>>
   */
  private array $inlineSchemaCache = [];

  public function __construct(
    private readonly ControllerManager $controllerManager,
    private readonly ModuleManager $moduleManager,
    private readonly Request $request,
    private readonly ComposerConfig $composerConfig,
    private readonly ProjectConfig $projectConfig,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function generate(string $rootModuleClass): array
  {
    $this->ensureControllerGraphResolved($rootModuleClass);
    $this->componentNames = [];
    $this->componentNameIndex = [];
    $this->componentSchemas = [];
    $this->inlineSchemaCache = [];

    $paths = [];

    foreach ($this->controllerManager->getControllerTokenList() as $controllerReflection) {
      $controllerClass = $controllerReflection->getName();
      $controllerPath = $this->controllerManager->getResolvedControllerPath($controllerClass) ?? '/';
      $controllerHosts = $this->controllerManager->getControllerHosts($controllerClass);
      $tagName = $this->buildTagName($controllerReflection->getShortName());

      foreach ($controllerReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $handler) {
        foreach ($handler->getAttributes() as $attribute) {
          if (!Validator::isValidRequestMapperAttribute($attribute)) {
            continue;
          }

          $httpMethod = $this->attributeToHttpMethod($attribute->getName());

          if ($httpMethod === null) {
            continue;
          }

          $routePath = $this->joinPaths($controllerPath, $this->getRouteAttributePath($attribute));
          $openApiPath = $this->toOpenApiPath($routePath);
          $operation = $this->buildOperation($controllerReflection, $handler, $routePath, $controllerHosts, $tagName);

          $paths[$openApiPath][strtolower($httpMethod)] = $operation;
        }
      }
    }

    ksort($paths);
    ksort($this->componentSchemas);

    $document = [
      'openapi' => '3.1.0',
      'info' => [
        'title' => $this->resolveApiTitle(),
        'version' => $this->resolveApiVersion(),
        'description' => 'Generated automatically by Assegai.',
      ],
      'servers' => $this->buildDefaultServers(),
      'paths' => $paths,
      'components' => [
        'schemas' => $this->componentSchemas,
      ],
    ];

    if (empty($this->componentSchemas)) {
      unset($document['components']);
    }

    return $document;
  }

  private function ensureControllerGraphResolved(string $rootModuleClass): void
  {
    $this->moduleManager->setRootModuleClass($rootModuleClass);

    if ($this->moduleManager->getModuleTokens() === []) {
      $this->moduleManager->buildModuleTokensList($rootModuleClass);
    }

    if ($this->controllerManager->getControllerTokenList() === []) {
      $this->controllerManager->buildControllerTokensList($this->moduleManager->getModuleTokens());
    }
  }

  /**
   * @param array<int, string> $controllerHosts
   * @return array<string, mixed>
   */
  private function buildOperation(
    ReflectionClass $controllerReflection,
    ReflectionMethod $handler,
    string $routePath,
    array $controllerHosts,
    string $tagName,
  ): array
  {
    $parameters = [];
    $requestBody = $this->buildRequestBody($handler);
    $pathPlaceholders = $this->extractPathPlaceholders($routePath);
    $documentedPathParameters = [];
    $hasQueryDto = false;

    foreach ($handler->getParameters() as $parameter) {
      $parameterAttributes = $parameter->getAttributes();
      $bodyAttribute = $this->findAttribute($parameterAttributes, Body::class);

      if ($bodyAttribute !== null) {
        continue;
      }

      $paramAttribute = $this->findAttribute($parameterAttributes, Param::class);
      $queryAttribute = $this->findAttribute($parameterAttributes, Query::class);
      $hostParamAttribute = $this->findAttribute($parameterAttributes, HostParam::class);

      if ($paramAttribute !== null) {
        $arguments = $paramAttribute->getArguments();
        $key = $arguments['key'] ?? $arguments[0] ?? $parameter->getName();

        if (isset($pathPlaceholders[$key])) {
          $parameters[] = $this->buildPathParameter($key, $pathPlaceholders[$key], $parameter);
          $documentedPathParameters[$key] = true;
        }
        continue;
      }

      if ($queryAttribute !== null) {
        $arguments = $queryAttribute->getArguments();
        $key = $arguments['key'] ?? $arguments[0] ?? null;

        if (is_string($key) && $key !== '') {
          $parameters[] = $this->buildQueryParameter($key, $parameter);
          continue;
        }

        $typeName = $this->getNamedTypeName($parameter->getType());

        if ($typeName !== null && class_exists($typeName)) {
          $parameters = [...$parameters, ...$this->expandQueryDtoParameters($typeName)];
          $hasQueryDto = true;
        }

        continue;
      }

      if ($hostParamAttribute !== null) {
        continue;
      }

      if (isset($pathPlaceholders[$parameter->getName()])) {
        $parameters[] = $this->buildPathParameter($parameter->getName(), $pathPlaceholders[$parameter->getName()], $parameter);
        $documentedPathParameters[$parameter->getName()] = true;
      }
    }

    foreach ($pathPlaceholders as $name => $constraint) {
      if (isset($documentedPathParameters[$name])) {
        continue;
      }

      $parameters[] = $this->buildPathParameter($name, $constraint, null);
    }

    $operationId = $this->buildOperationId($controllerReflection, $handler);
    $responseStatus = $this->resolveResponseStatus($handler);
    $operation = [
      'tags' => [$tagName],
      'operationId' => $operationId,
      'summary' => $this->humanizeName($handler->getName()),
      'responses' => $this->buildResponses($handler, $responseStatus),
    ];

    if ($parameters !== []) {
      $operation['parameters'] = array_values($parameters);
    }

    if ($requestBody !== null) {
      $operation['requestBody'] = $requestBody;
    }

    if ($controllerHosts !== []) {
      $servers = $this->buildServersForHosts($controllerHosts);

      if ($servers !== []) {
        $operation['servers'] = $servers;
      }
    }

    if ($hasQueryDto) {
      $operation['x-assegai-query-style'] = 'dto';
    }

    return $operation;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function buildRequestBody(ReflectionMethod $handler): ?array
  {
    $bodyParameters = [];

    foreach ($handler->getParameters() as $parameter) {
      $attribute = $this->findAttribute($parameter->getAttributes(), Body::class);

      if ($attribute === null) {
        continue;
      }

      $arguments = $attribute->getArguments();
      $bodyParameters[] = [
        'parameter' => $parameter,
        'key' => $arguments['key'] ?? $arguments[0] ?? null,
      ];
    }

    if ($bodyParameters === []) {
      return null;
    }

    $schema = null;
    $required = true;

    if (count($bodyParameters) === 1 && empty($bodyParameters[0]['key'])) {
      /** @var ReflectionParameter $parameter */
      $parameter = $bodyParameters[0]['parameter'];
      $schema = $this->schemaFromParameter($parameter);
      $required = $this->isRequiredParameter($parameter);
    } else {
      $schema = [
        'type' => 'object',
        'properties' => [],
      ];
      $requiredProperties = [];
      $example = [];

      foreach ($bodyParameters as $bodyParameter) {
        /** @var ReflectionParameter $parameter */
        $parameter = $bodyParameter['parameter'];
        $propertyName = is_string($bodyParameter['key']) && $bodyParameter['key'] !== ''
          ? $bodyParameter['key']
          : $parameter->getName();
        $propertySchema = $this->schemaFromParameter($parameter);
        $schema['properties'][$propertyName] = $propertySchema;
        $example[$propertyName] = $this->buildSchemaExample($propertySchema);

        if ($this->isRequiredParameter($parameter)) {
          $requiredProperties[] = $propertyName;
        }
      }

      if ($requiredProperties !== []) {
        $schema['required'] = $requiredProperties;
      }

      if ($example !== []) {
        $schema['example'] = $example;
      }

      $required = $requiredProperties !== [];
    }

    $example = $this->buildSchemaExample($schema);
    $content = [];

    foreach (['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'] as $contentType) {
      $content[$contentType] = [
        'schema' => $schema,
        'example' => $example,
      ];
    }

    return [
      'required' => $required,
      'content' => $content,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function buildResponses(ReflectionMethod $handler, int $statusCode): array
  {
    $responseContent = $this->buildResponseContent($handler);
    $responses = [
      (string) $statusCode => [
        'description' => $this->statusDescription($statusCode),
      ],
    ];

    if ($responseContent !== null) {
      $responses[(string) $statusCode]['content'] = $responseContent;
    }

    $redirect = $this->findAttribute($handler->getAttributes(), Redirect::class);

    if ($redirect !== null) {
      $arguments = $redirect->getArguments();
      $redirectStatus = $arguments['status'] ?? $arguments[1] ?? 302;
      $redirectUrl = $arguments['url'] ?? $arguments[0] ?? '/';
      $responses[(string) $redirectStatus] = [
        'description' => 'Redirect response.',
        'headers' => [
          'Location' => [
            'description' => 'Redirect target.',
            'schema' => [
              'type' => 'string',
              'example' => $redirectUrl,
            ],
          ],
        ],
      ];
    }

    return $responses;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function buildResponseContent(ReflectionMethod $handler): ?array
  {
    $returnType = $handler->getReturnType();

    if ($returnType === null) {
      return null;
    }

    $frameworkResponseContent = $this->frameworkResponseContent($returnType);

    if ($frameworkResponseContent !== null || $this->isOpaqueFrameworkResponseType($returnType)) {
      return $frameworkResponseContent;
    }

    $schema = $this->schemaFromType($returnType);

    if ($schema === null) {
      return null;
    }

    $contentType = $this->schemaLooksJson($schema) ? 'application/json' : 'text/plain';

    return [
      $contentType => [
        'schema' => $schema,
        'example' => $this->buildSchemaExample($schema),
      ],
    ];
  }

  /**
   * @return array<string, mixed>|null
   */
  private function frameworkResponseContent(ReflectionType $type): ?array
  {
    $namedTypes = $this->namedTypesFrom($type);

    foreach ($namedTypes as $namedType) {
      if ($namedType->isBuiltin()) {
        continue;
      }

      $typeName = $namedType->getName();

      if (
        $this->isHtmlResponseType($typeName) ||
        str_starts_with($typeName, 'Assegai\\Core\\Rendering\\')
      ) {
        return [
          'text/html' => [
            'schema' => [
              'type' => 'string',
              'description' => 'Rendered HTML response.',
            ],
            'example' => "<!DOCTYPE html>\n<html lang=\"en\">\n  <body>...</body>\n</html>",
          ],
        ];
      }
    }

    return null;
  }

  private function isOpaqueFrameworkResponseType(ReflectionType $type): bool
  {
    foreach ($this->namedTypesFrom($type) as $namedType) {
      if ($namedType->isBuiltin()) {
        continue;
      }

      $typeName = $namedType->getName();

      if (
        is_a($typeName, Response::class, true) ||
        is_a($typeName, ApiResponse::class, true) ||
        str_starts_with($typeName, 'Assegai\\Core\\Http\\Responses\\')
      ) {
        return true;
      }
    }

    return false;
  }

  private function isHtmlResponseType(string $typeName): bool
  {
    return is_a($typeName, View::class, true)
      || is_a($typeName, ComponentInterface::class, true);
  }

  /**
   * @return array<int, ReflectionNamedType>
   */
  private function namedTypesFrom(ReflectionType $type): array
  {
    if ($type instanceof ReflectionNamedType) {
      return [$type];
    }

    if ($type instanceof ReflectionUnionType) {
      return array_values(array_filter(
        $type->getTypes(),
        static fn(ReflectionType $inner): bool => $inner instanceof ReflectionNamedType && $inner->getName() !== 'null'
      ));
    }

    return [];
  }

  private function schemaLooksJson(array $schema): bool
  {
    if (isset($schema['$ref'])) {
      return true;
    }

    return !in_array($schema['type'] ?? null, ['string', 'integer', 'number', 'boolean'], true);
  }

  private function resolveApiTitle(): string
  {
    $projectName = $this->projectConfig->get('name');

    if (is_string($projectName) && $projectName !== '') {
      return $projectName . ' API';
    }

    $composerName = $this->composerConfig->get('name');

    if (is_string($composerName) && $composerName !== '') {
      return $composerName . ' API';
    }

    return 'Assegai API';
  }

  private function resolveApiVersion(): string
  {
    $version = $this->composerConfig->get('version');

    return is_string($version) && $version !== '' ? $version : '0.1.0';
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function buildDefaultServers(): array
  {
    $scheme = $this->request->getProtocol() ?: 'http';
    $host = $this->request->header('Host');

    if ($host === '') {
      $host = $this->request->getHostName() ?: 'localhost';
      $serverPort = $_SERVER['SERVER_PORT'] ?? null;

      if (is_numeric($serverPort) && !in_array((int) $serverPort, [80, 443], true)) {
        $host .= ':' . $serverPort;
      }
    }

    return [[
      'url' => sprintf('%s://%s', $scheme, $host),
    ]];
  }

  /**
   * @param array<int, string> $hosts
   * @return array<int, array<string, mixed>>
   */
  private function buildServersForHosts(array $hosts): array
  {
    $scheme = $this->request->getProtocol() ?: 'http';
    $servers = [];

    foreach ($hosts as $host) {
      if (str_contains($host, ':')) {
        $servers[] = $this->buildServerForDynamicHost($scheme, $host);
        continue;
      }

      $servers[] = ['url' => sprintf('%s://%s', $scheme, $host)];
    }

    return $servers;
  }

  /**
   * @return array<string, mixed>
   */
  private function buildServerForDynamicHost(string $scheme, string $hostPattern): array
  {
    $variables = [];
    $urlHost = preg_replace_callback(
      '/:([A-Za-z_][A-Za-z0-9_]*)/',
      static function (array $matches) use (&$variables): string {
        $variables[$matches[1]] = [
          'default' => $matches[1],
        ];

        return '{' . $matches[1] . '}';
      },
      $hostPattern
    );

    return [
      'url' => sprintf('%s://%s', $scheme, $urlHost),
      'variables' => $variables,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function buildPathParameter(string $name, ?string $constraint, ?ReflectionParameter $parameter): array
  {
    $schema = $parameter ? $this->schemaFromParameter($parameter) : ['type' => 'string'];
    $schema = $this->applyRouteConstraint($schema, $constraint);

    return [
      'name' => $name,
      'in' => 'path',
      'required' => true,
      'schema' => $schema,
      'description' => 'Path parameter.',
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function buildQueryParameter(string $name, ReflectionParameter $parameter): array
  {
    return [
      'name' => $name,
      'in' => 'query',
      'required' => $this->isRequiredParameter($parameter),
      'schema' => $this->schemaFromParameter($parameter),
      'description' => 'Query parameter.',
    ];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function expandQueryDtoParameters(string $className): array
  {
    $reflection = new ReflectionClass($className);
    $parameters = [];

    foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
      $schema = $this->schemaFromProperty($property);
      $parameters[] = [
        'name' => $property->getName(),
        'in' => 'query',
        'required' => $this->isRequiredProperty($property, $reflection),
        'schema' => $schema,
        'description' => $schema['description'] ?? 'Query parameter.',
      ];
    }

    return $parameters;
  }

  /**
   * @return array<string, mixed>
   */
  private function schemaFromParameter(ReflectionParameter $parameter): array
  {
    $schema = $this->schemaFromType($parameter->getType());

    if ($schema === null) {
      return ['type' => 'object'];
    }

    return $schema;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function schemaFromType(?ReflectionType $type): ?array
  {
    if ($type === null) {
      return null;
    }

    if ($type instanceof ReflectionUnionType) {
      $nullable = false;
      $selected = null;

      foreach ($type->getTypes() as $innerType) {
        if ($innerType->getName() === 'null') {
          $nullable = true;
          continue;
        }

        $selected = $selected ?? $innerType;
      }

      $schema = $selected instanceof ReflectionNamedType
        ? $this->schemaFromNamedType($selected)
        : ['type' => 'object'];

      if ($schema !== null && $nullable) {
        $schema['nullable'] = true;
      }

      return $schema;
    }

    if (!$type instanceof ReflectionNamedType) {
      return ['type' => 'object'];
    }

    return $this->schemaFromNamedType($type);
  }

  /**
   * @return array<string, mixed>|null
   */
  private function schemaFromNamedType(ReflectionNamedType $type): ?array
  {
    $typeName = $type->getName();

    if ($type->isBuiltin()) {
      return $this->mapBuiltinTypeToSchema($typeName, !$type->allowsNull());
    }

    if ($typeName === 'stdClass') {
      return ['type' => 'object'];
    }

    if (!class_exists($typeName) && !enum_exists($typeName)) {
      return ['type' => 'object'];
    }

    return $this->registerComponentSchema($typeName, $type->allowsNull());
  }

  /**
   * @return array<string, mixed>
   */
  private function mapBuiltinTypeToSchema(string $typeName, bool $required = true): array
  {
    $schema = match ($typeName) {
      'int' => ['type' => 'integer'],
      'float' => ['type' => 'number'],
      'bool' => ['type' => 'boolean'],
      'array' => ['type' => 'array', 'items' => new \stdClass()],
      'string' => ['type' => 'string'],
      default => ['type' => 'object'],
    };

    if (!$required) {
      $schema['nullable'] = true;
    }

    return $schema;
  }

  /**
   * @return array<string, mixed>
   */
  private function registerComponentSchema(string $className, bool $nullable = false): array
  {
    $componentName = $this->componentNameFor($className);

    if (!isset($this->componentSchemas[$componentName])) {
      $this->componentSchemas[$componentName] = $this->inlineSchemaForClass($className);
    }

    $reference = ['$ref' => '#/components/schemas/' . $componentName];

    if ($nullable) {
      $reference['nullable'] = true;
    }

    return $reference;
  }

  /**
   * @return array<string, mixed>
   */
  private function inlineSchemaForClass(string $className): array
  {
    if (isset($this->inlineSchemaCache[$className])) {
      return $this->inlineSchemaCache[$className];
    }

    if (enum_exists($className) || is_subclass_of($className, UnitEnum::class, true)) {
      $schema = $this->enumSchema($className);
      $this->inlineSchemaCache[$className] = $schema;
      return $schema;
    }

    $reflection = new ReflectionClass($className);
    $schema = [
      'type' => 'object',
      'properties' => [],
    ];
    $required = [];
    $example = [];

    foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
      $propertySchema = $this->schemaFromProperty($property);
      $schema['properties'][$property->getName()] = $propertySchema;
      $example[$property->getName()] = $this->buildSchemaExample($propertySchema);

      if ($this->isRequiredProperty($property, $reflection)) {
        $required[] = $property->getName();
      }
    }

    if ($required !== []) {
      $schema['required'] = $required;
    }

    if ($example !== []) {
      $schema['example'] = $example;
    }

    $this->inlineSchemaCache[$className] = $schema;

    return $schema;
  }

  /**
   * @return array<string, mixed>
   */
  private function schemaFromProperty(ReflectionProperty $property): array
  {
    $schema = $this->schemaFromType($property->getType()) ?? ['type' => 'object'];
    $validationRules = [];

    foreach ($property->getAttributes() as $attribute) {
      $validationRule = match ($attribute->getName()) {
        IsString::class => $this->applyDescription(['rule' => 'string'], $schema, 'Must be a string.'),
        IsNotEmpty::class => $this->applyNotEmptyRule($schema),
        IsInt::class => $this->applyDescription(['rule' => 'integer'], ['type' => 'integer'] + $schema, 'Must be an integer.'),
        IsNumber::class,
        IsNumeric::class => $this->applyDescription(['rule' => 'number'], ['type' => 'number'] + $schema, 'Must be numeric.'),
        IsArray::class => $this->applyDescription(['rule' => 'array'], ['type' => 'array', 'items' => new \stdClass()] + $schema, 'Must be an array.'),
        IsEmail::class => $this->applyDescription(['rule' => 'email'], $schema + ['format' => 'email'], 'Must be a valid email address.'),
        IsUrl::class => $this->applyDescription(['rule' => 'url'], $schema + ['format' => 'uri'], 'Must be a valid URL.'),
        IsDate::class => $this->applyDateRule($attribute, $schema),
        IsEnum::class => $this->applyEnumRule($attribute, $schema),
        IsBetween::class => $this->applyBetweenRule($attribute, $schema),
        IsEqualTo::class => $this->applyEqualToRule($attribute, $schema),
        IsAlpha::class => $this->applyPatternRule($schema, '^[A-Za-z]+$', 'Must contain letters only.'),
        IsAlphanumeric::class => $this->applyPatternRule($schema, '^[A-Za-z0-9]+$', 'Must contain letters and numbers only.'),
        IsAscii::class => $this->applyPatternRule($schema, '^[\\x00-\\x7F]+$', 'Must contain ASCII characters only.'),
        IsDomain::class => $this->applyDescription(['rule' => 'domain'], $schema + ['format' => 'hostname'], 'Must be a valid domain name.'),
        IsOptional::class => ['rule' => 'optional'],
        default => null,
      };

      if ($validationRule === null) {
        continue;
      }

      if (isset($validationRule['schema'])) {
        $schema = $validationRule['schema'];
      }

      if (isset($validationRule['rule'])) {
        $validationRules[] = $validationRule['rule'];
      }
    }

    if ($validationRules !== []) {
      $schema['x-assegai-validation'] = $validationRules;
    }

    return $schema;
  }

  /**
   * @return array{schema: array<string, mixed>, rule: string}
   */
  private function applyNotEmptyRule(array $schema): array
  {
    $type = $schema['type'] ?? null;

    if ($type === 'array') {
      $schema['minItems'] = max(1, (int) ($schema['minItems'] ?? 0));
      return $this->applyDescription(['rule' => 'not_empty'], $schema, 'Must not be empty.');
    }

    $schema['minLength'] = max(1, (int) ($schema['minLength'] ?? 0));

    return $this->applyDescription(['rule' => 'not_empty'], $schema, 'Must not be empty.');
  }

  /**
   * @return array{schema: array<string, mixed>, rule: string}
   */
  private function applyDateRule(ReflectionAttribute $attribute, array $schema): array
  {
    $arguments = $attribute->getArguments();
    $format = $arguments['format'] ?? $arguments[0] ?? \DateTimeInterface::ATOM;
    $schema['type'] = 'string';
    $schema['format'] = $format === 'Y-m-d' ? 'date' : 'date-time';

    return $this->applyDescription(['rule' => 'date'], $schema, 'Must be a valid date.');
  }

  /**
   * @return array{schema: array<string, mixed>, rule: string}
   */
  private function applyEnumRule(ReflectionAttribute $attribute, array $schema): array
  {
    $arguments = $attribute->getArguments();
    $enumClass = $arguments['enum'] ?? $arguments[0] ?? null;

    if (is_string($enumClass) && (enum_exists($enumClass) || is_subclass_of($enumClass, UnitEnum::class, true))) {
      $schema = $this->enumSchema($enumClass);
    }

    return $this->applyDescription(['rule' => 'enum'], $schema, 'Must be one of the allowed values.');
  }

  /**
   * @return array{schema: array<string, mixed>, rule: string}
   */
  private function applyBetweenRule(ReflectionAttribute $attribute, array $schema): array
  {
    $arguments = $attribute->getArguments();
    $min = $arguments['min'] ?? $arguments[0] ?? null;
    $max = $arguments['max'] ?? $arguments[1] ?? null;

    if (($schema['type'] ?? null) === 'string') {
      if (is_numeric($min)) {
        $schema['minLength'] = (int) $min;
      }

      if (is_numeric($max)) {
        $schema['maxLength'] = (int) $max;
      }
    } else {
      if (is_numeric($min)) {
        $schema['minimum'] = $min + 0;
      }

      if (is_numeric($max)) {
        $schema['maximum'] = $max + 0;
      }
    }

    return $this->applyDescription(['rule' => 'between'], $schema, 'Must be within the allowed range.');
  }

  /**
   * @return array{schema: array<string, mixed>, rule: string}
   */
  private function applyEqualToRule(ReflectionAttribute $attribute, array $schema): array
  {
    $arguments = $attribute->getArguments();
    $target = $arguments['target'] ?? $arguments[0] ?? null;
    $schema['const'] = $target;

    return $this->applyDescription(['rule' => 'equal_to'], $schema, 'Must match the expected value.');
  }

  /**
   * @return array{schema: array<string, mixed>, rule: string}
   */
  private function applyPatternRule(array $schema, string $pattern, string $description): array
  {
    $schema['type'] = 'string';
    $schema['pattern'] = $pattern;

    return $this->applyDescription(['rule' => 'pattern'], $schema, $description);
  }

  /**
   * @param array{rule: string} $rule
   * @return array{schema: array<string, mixed>, rule: string}
   */
  private function applyDescription(array $rule, array $schema, string $description): array
  {
    if (!isset($schema['description'])) {
      $schema['description'] = $description;
    } elseif (!str_contains($schema['description'], $description)) {
      $schema['description'] .= ' ' . $description;
    }

    return [
      'schema' => $schema,
      'rule' => $rule['rule'],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function enumSchema(string $enumClass): array
  {
    $cases = $enumClass::cases();
    $values = array_map(
      static fn(UnitEnum $case) => property_exists($case, 'value') ? $case->value : $case->name,
      $cases
    );
    $firstValue = $values[0] ?? null;
    $type = match (true) {
      is_int($firstValue) => 'integer',
      is_float($firstValue) => 'number',
      default => 'string',
    };

    return [
      'type' => $type,
      'enum' => $values,
      'example' => $firstValue,
    ];
  }

  private function isRequiredParameter(ReflectionParameter $parameter): bool
  {
    $type = $parameter->getType();

    if ($parameter->isOptional() || $parameter->isDefaultValueAvailable()) {
      return false;
    }

    if ($type instanceof ReflectionNamedType) {
      return !$type->allowsNull();
    }

    if ($type instanceof ReflectionUnionType) {
      foreach ($type->getTypes() as $innerType) {
        if ($innerType->getName() === 'null') {
          return false;
        }
      }
    }

    return true;
  }

  private function isRequiredProperty(ReflectionProperty $property, ReflectionClass $class): bool
  {
    foreach ($property->getAttributes(IsOptional::class) as $_) {
      return false;
    }

    $defaults = $class->getDefaultProperties();

    if (array_key_exists($property->getName(), $defaults)) {
      return false;
    }

    $type = $property->getType();

    if ($type instanceof ReflectionNamedType) {
      return !$type->allowsNull();
    }

    if ($type instanceof ReflectionUnionType) {
      foreach ($type->getTypes() as $innerType) {
        if ($innerType->getName() === 'null') {
          return false;
        }
      }
    }

    return true;
  }

  private function resolveResponseStatus(ReflectionMethod $handler): int
  {
    $defaultStatus = 200;
    $requestMapper = $this->findRequestMapperAttribute($handler);

    if ($requestMapper?->getName() === Post::class) {
      $defaultStatus = 201;
    }

    foreach ($handler->getAttributes() as $attribute) {
      if (!in_array($attribute->getName(), [HttpCode::class, ResponseStatus::class], true)) {
        continue;
      }

      $arguments = $attribute->getArguments();
      $status = $arguments['code'] ?? $arguments[0] ?? $defaultStatus;

      if (is_object($status) && property_exists($status, 'code')) {
        return (int) $status->code;
      }

      return (int) $status;
    }

    return $defaultStatus;
  }

  private function statusDescription(int $statusCode): string
  {
    return match ($statusCode) {
      200 => 'Successful response.',
      201 => 'Resource created successfully.',
      202 => 'Request accepted successfully.',
      204 => 'No content.',
      302, 303, 307, 308 => 'Redirect response.',
      default => 'Response.',
    };
  }

  private function buildTagName(string $controllerShortName): string
  {
    $name = preg_replace('/Controller$/', '', $controllerShortName) ?: $controllerShortName;

    return $this->humanizeName($name);
  }

  private function buildOperationId(ReflectionClass $controllerReflection, ReflectionMethod $handler): string
  {
    $controllerName = preg_replace('/Controller$/', '', $controllerReflection->getShortName()) ?: $controllerReflection->getShortName();

    return lcfirst($controllerName) . ucfirst($handler->getName());
  }

  private function humanizeName(string $name): string
  {
    $spaced = preg_replace('/(?<!^)([A-Z])/', ' $1', $name) ?: $name;
    $spaced = str_replace(['_', '-'], ' ', $spaced);

    return ucwords(trim($spaced));
  }

  private function attributeToHttpMethod(string $attributeClass): ?string
  {
    return match ($attributeClass) {
      Get::class, Sse::class => 'GET',
      Post::class => 'POST',
      Put::class => 'PUT',
      Patch::class => 'PATCH',
      Delete::class => 'DELETE',
      Options::class => 'OPTIONS',
      Head::class => 'HEAD',
      default => null,
    };
  }

  private function getRouteAttributePath(ReflectionAttribute $attribute): string
  {
    $arguments = $attribute->getArguments();

    return (string) ($arguments['path'] ?? $arguments[0] ?? '');
  }

  private function joinPaths(string $left, string $right = ''): string
  {
    $segments = [];

    foreach ([$left, $right] as $path) {
      $normalized = trim($path, '/');

      if ($normalized === '') {
        continue;
      }

      $segments[] = $normalized;
    }

    return '/' . implode('/', $segments);
  }

  private function toOpenApiPath(string $routePath): string
  {
    $segments = array_values(array_filter(explode('/', trim($routePath, '/')), static fn(string $segment): bool => $segment !== ''));

    if ($segments === []) {
      return '/';
    }

    $normalized = array_map(function (string $segment): string {
      if ($segment === '*') {
        return '{wildcard}';
      }

      if (preg_match('/^:([A-Za-z_][A-Za-z0-9_-]*)(?:<([^>]+)>)?$/', $segment, $matches)) {
        return '{' . $matches[1] . '}';
      }

      return $segment;
    }, $segments);

    return '/' . implode('/', $normalized);
  }

  /**
   * @return array<string, string|null>
   */
  private function extractPathPlaceholders(string $routePath): array
  {
    $placeholders = [];
    $segments = array_values(array_filter(explode('/', trim($routePath, '/')), static fn(string $segment): bool => $segment !== ''));

    foreach ($segments as $segment) {
      if (!preg_match('/^:([A-Za-z_][A-Za-z0-9_-]*)(?:<([^>]+)>)?$/', $segment, $matches)) {
        continue;
      }

      $placeholders[$matches[1]] = $matches[2] ?? null;
    }

    return $placeholders;
  }

  /**
   * @return array<string, mixed>
   */
  private function applyRouteConstraint(array $schema, ?string $constraint): array
  {
    if ($constraint === null || $constraint === '') {
      return $schema;
    }

    return match ($constraint) {
      'int' => ['type' => 'integer'] + $schema,
      'uuid' => ['type' => 'string', 'format' => 'uuid'] + $schema,
      'slug' => ['type' => 'string', 'pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$'] + $schema,
      'alpha' => ['type' => 'string', 'pattern' => '^[A-Za-z]+$'] + $schema,
      'alnum' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9]+$'] + $schema,
      'hex' => ['type' => 'string', 'pattern' => '^[A-Fa-f0-9]+$'] + $schema,
      'ulid' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'] + $schema,
      default => $schema,
    };
  }

  /**
   * @return array<string, mixed>
   */
  private function buildSchemaExample(array $schema): mixed
  {
    if (isset($schema['example'])) {
      return $schema['example'];
    }

    if (isset($schema['const'])) {
      return $schema['const'];
    }

    if (isset($schema['enum'][0])) {
      return $schema['enum'][0];
    }

    if (isset($schema['$ref']) && is_string($schema['$ref'])) {
      $componentName = basename($schema['$ref']);

      if (isset($this->componentSchemas[$componentName])) {
        return $this->buildSchemaExample($this->componentSchemas[$componentName]);
      }
    }

    return match ($schema['type'] ?? 'object') {
      'string' => 'string',
      'integer' => 1,
      'number' => 1.0,
      'boolean' => true,
      'array' => isset($schema['items']) && is_array($schema['items'])
        ? [$this->buildSchemaExample($schema['items'])]
        : [],
      'object' => $this->buildObjectExample($schema),
      default => null,
    };
  }

  /**
   * @return array<string, mixed>
   */
  private function buildObjectExample(array $schema): array
  {
    $example = [];

    foreach ($schema['properties'] ?? [] as $propertyName => $propertySchema) {
      if (!is_array($propertySchema)) {
        continue;
      }

      $example[$propertyName] = $this->buildSchemaExample($propertySchema);
    }

    return $example;
  }

  private function componentNameFor(string $className): string
  {
    if (isset($this->componentNames[$className])) {
      return $this->componentNames[$className];
    }

    $baseName = (new ReflectionClass($className))->getShortName();
    $candidate = $baseName;
    $suffix = 2;

    while (isset($this->componentNameIndex[$candidate]) && $this->componentNameIndex[$candidate] !== $className) {
      $candidate = $baseName . $suffix;
      $suffix++;
    }

    $this->componentNames[$className] = $candidate;
    $this->componentNameIndex[$candidate] = $className;

    return $candidate;
  }

  /**
   * @param ReflectionAttribute[] $attributes
   */
  private function findAttribute(array $attributes, string $attributeClass): ?ReflectionAttribute
  {
    foreach ($attributes as $attribute) {
      if ($attribute->getName() === $attributeClass) {
        return $attribute;
      }
    }

    return null;
  }

  private function findRequestMapperAttribute(ReflectionMethod $handler): ?ReflectionAttribute
  {
    foreach ($handler->getAttributes() as $attribute) {
      if (Validator::isValidRequestMapperAttribute($attribute)) {
        return $attribute;
      }
    }

    return null;
  }

  private function getNamedTypeName(?ReflectionType $type): ?string
  {
    if ($type instanceof ReflectionNamedType) {
      return $type->getName();
    }

    if ($type instanceof ReflectionUnionType) {
      foreach ($type->getTypes() as $innerType) {
        if ($innerType->getName() !== 'null') {
          return $innerType->getName();
        }
      }
    }

    return null;
  }
}
