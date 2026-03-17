<?php

namespace Assegai\Core\ApiDocs;

class TypeScriptClientGenerator
{
  /**
   * @param array<string, mixed> $document
   */
  public function generate(array $document): string
  {
    $baseUrl = $document['servers'][0]['url'] ?? 'http://localhost:5050';
    $schemas = $document['components']['schemas'] ?? [];
    $typeBlocks = [];

    foreach ($schemas as $name => $schema) {
      if (!is_array($schema)) {
        continue;
      }

      $typeBlocks[] = $this->emitNamedType((string) $name, $schema);
    }

    $operations = [];

    foreach ($document['paths'] ?? [] as $path => $pathItem) {
      if (!is_array($pathItem)) {
        continue;
      }

      foreach ($pathItem as $method => $operation) {
        if (!is_array($operation)) {
          continue;
        }

        $operations[] = $this->emitOperation((string) $path, strtoupper((string) $method), $operation);
      }
    }

    $types = implode("\n\n", array_filter($typeBlocks));
    $methods = implode("\n\n", array_filter($operations));
    $escapedBaseUrl = addslashes($baseUrl);

    return <<<TS
/* eslint-disable */

export interface ApiRequestOptions {
  headers?: HeadersInit;
  signal?: AbortSignal;
}

function buildUrl(
  baseUrl: string,
  pathTemplate: string,
  pathParams?: Record<string, string | number | boolean>,
  query?: Record<string, unknown>,
): string {
  let path = pathTemplate.replace(/\\{(.*?)\\}/g, (_, key: string) => {
    const value = pathParams?.[key];

    if (value === undefined || value === null) {
      throw new Error(`Missing path parameter: \${key}`);
    }

    return encodeURIComponent(String(value));
  });

  const url = new URL(path, baseUrl.endsWith('/') ? baseUrl : baseUrl + '/');

  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value === undefined || value === null) {
        continue;
      }

      if (Array.isArray(value)) {
        value.forEach((entry) => url.searchParams.append(key, String(entry)));
        continue;
      }

      url.searchParams.set(key, String(value));
    }
  }

  return url.toString();
}

async function request<T>(
  baseUrl: string,
  method: string,
  pathTemplate: string,
  options: ApiRequestOptions & {
    path?: Record<string, string | number | boolean>;
    query?: Record<string, unknown>;
    body?: unknown;
  } = {},
): Promise<T> {
  const headers = new Headers(options.headers ?? {});
  const init: RequestInit = {
    method,
    headers,
    signal: options.signal,
  };

  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json');
    init.body = JSON.stringify(options.body);
  }

  const response = await fetch(buildUrl(baseUrl, pathTemplate, options.path, options.query), init);

  if (!response.ok) {
    throw new Error(`Request failed with status \${response.status}`);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const contentType = response.headers.get('content-type') ?? '';

  if (contentType.includes('application/json')) {
    return await response.json() as T;
  }

  return await response.text() as T;
}

{$types}

export function createAssegaiClient(baseUrl = '{$escapedBaseUrl}') {
  return {
{$methods}
  };
}
TS;
  }

  /**
   * @param array<string, mixed> $schema
   */
  private function emitNamedType(string $name, array $schema): string
  {
    if (($schema['type'] ?? null) === 'object' || isset($schema['properties'])) {
      $lines = ["export interface {$name} {"];
      $required = array_flip($schema['required'] ?? []);

      foreach ($schema['properties'] ?? [] as $propertyName => $propertySchema) {
        if (!is_array($propertySchema)) {
          continue;
        }

        $optional = isset($required[$propertyName]) ? '' : '?';
        $lines[] = "  {$propertyName}{$optional}: " . $this->schemaToTypeScript($propertySchema) . ';';
      }

      $lines[] = '}';

      return implode("\n", $lines);
    }

    return 'export type ' . $name . ' = ' . $this->schemaToTypeScript($schema) . ';';
  }

  /**
   * @param array<string, mixed> $operation
   */
  private function emitOperation(string $path, string $method, array $operation): string
  {
    $operationName = $operation['operationId'] ?? lcfirst($method) . str_replace(['/', '{', '}', '-', ':'], ' ', $path);
    $operationName = preg_replace('/[^A-Za-z0-9]+/', ' ', (string) $operationName) ?: 'request';
    $operationName = lcfirst(str_replace(' ', '', ucwords(trim($operationName))));
    $parameters = $operation['parameters'] ?? [];
    $pathProperties = [];
    $queryProperties = [];

    foreach ($parameters as $parameter) {
      if (!is_array($parameter)) {
        continue;
      }

      $property = $parameter['name'] . (($parameter['required'] ?? false) ? '' : '?') . ': ' . $this->schemaToTypeScript($parameter['schema'] ?? []);

      if (($parameter['in'] ?? null) === 'path') {
        $pathProperties[] = $property;
      }

      if (($parameter['in'] ?? null) === 'query') {
        $queryProperties[] = $property;
      }
    }

    $requestBody = $operation['requestBody']['content']['application/json']['schema'] ?? null;
    $responseType = $this->resolveResponseType($operation);
    $optionLines = ['headers?: HeadersInit;', 'signal?: AbortSignal;'];

    if ($pathProperties !== []) {
      $optionLines[] = 'path: { ' . implode('; ', $pathProperties) . ' };';
    }

    if ($queryProperties !== []) {
      $optionLines[] = 'query?: { ' . implode('; ', $queryProperties) . ' };';
    }

    if (is_array($requestBody)) {
      $optionLines[] = 'body: ' . $this->schemaToTypeScript($requestBody) . ';';
    }

    $optionsType = '{ ' . implode(' ', $optionLines) . ' }';
    $pathLiteral = addslashes($path);

    return <<<TS
    async {$operationName}(options: {$optionsType}): Promise<{$responseType}> {
      return request<{$responseType}>(baseUrl, '{$method}', '{$pathLiteral}', options);
    },
TS;
  }

  /**
   * @param array<string, mixed> $operation
   */
  private function resolveResponseType(array $operation): string
  {
    foreach ($operation['responses'] ?? [] as $response) {
      if (!is_array($response)) {
        continue;
      }

      foreach ($response['content'] ?? [] as $contentType => $content) {
        if (!is_array($content)) {
          continue;
        }

        if (is_array($content['schema'] ?? null)) {
          return $this->schemaToTypeScript($content['schema']);
        }

        if (is_string($contentType) && str_starts_with($contentType, 'text/')) {
          return 'string';
        }
      }
    }

    return 'unknown';
  }

  /**
   * @param array<string, mixed> $schema
   */
  private function schemaToTypeScript(array $schema): string
  {
    if (isset($schema['$ref']) && is_string($schema['$ref'])) {
      return basename($schema['$ref']);
    }

    if (isset($schema['enum']) && is_array($schema['enum'])) {
      return implode(' | ', array_map(static fn(mixed $value): string => json_encode($value), $schema['enum']));
    }

    return match ($schema['type'] ?? 'unknown') {
      'integer',
      'number' => 'number',
      'boolean' => 'boolean',
      'string' => 'string',
      'array' => (isset($schema['items']) && is_array($schema['items']) ? $this->schemaToTypeScript($schema['items']) : 'unknown') . '[]',
      'object' => $this->objectSchemaToTypeScript($schema),
      default => 'unknown',
    };
  }

  /**
   * @param array<string, mixed> $schema
   */
  private function objectSchemaToTypeScript(array $schema): string
  {
    $properties = $schema['properties'] ?? [];

    if (!is_array($properties) || $properties === []) {
      return 'Record<string, unknown>';
    }

    $required = array_flip($schema['required'] ?? []);
    $segments = [];

    foreach ($properties as $propertyName => $propertySchema) {
      if (!is_array($propertySchema)) {
        continue;
      }

      $optional = isset($required[$propertyName]) ? '' : '?';
      $segments[] = $propertyName . $optional . ': ' . $this->schemaToTypeScript($propertySchema);
    }

    return '{ ' . implode('; ', $segments) . ' }';
  }
}
