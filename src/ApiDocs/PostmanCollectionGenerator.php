<?php

namespace Assegai\Core\ApiDocs;

class PostmanCollectionGenerator
{
  /**
   * @param array<string, mixed> $document
   * @return array<string, mixed>
   */
  public function generate(array $document): array
  {
    $baseUrl = $document['servers'][0]['url'] ?? 'http://localhost:5050';
    $tagFolders = [];

    foreach ($document['paths'] ?? [] as $path => $pathItem) {
      if (!is_array($pathItem)) {
        continue;
      }

      foreach ($pathItem as $method => $operation) {
        if (!is_array($operation)) {
          continue;
        }

        $tag = $operation['tags'][0] ?? 'API';
        $tagFolders[$tag][] = $this->buildRequestItem($path, strtoupper((string) $method), $operation);
      }
    }

    $items = [];

    foreach ($tagFolders as $tag => $requests) {
      $items[] = [
        'name' => $tag,
        'item' => $requests,
      ];
    }

    return [
      'info' => [
        '_postman_id' => uniqid('assegai-', true),
        'name' => ($document['info']['title'] ?? 'Assegai API') . ' Collection',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
      ],
      'variable' => [
        [
          'key' => 'baseUrl',
          'value' => $baseUrl,
        ],
      ],
      'item' => $items,
    ];
  }

  /**
   * @param array<string, mixed> $operation
   * @return array<string, mixed>
   */
  private function buildRequestItem(string $path, string $method, array $operation): array
  {
    $resolvedPath = preg_replace('/\{([^}]+)\}/', '{{$1}}', $path) ?: $path;
    $query = [];

    foreach ($operation['parameters'] ?? [] as $parameter) {
      if (($parameter['in'] ?? null) !== 'query') {
        continue;
      }

      $query[] = [
        'key' => $parameter['name'],
        'value' => (string) (($parameter['schema']['example'] ?? '') ?: ''),
        'description' => $parameter['description'] ?? '',
        'disabled' => !($parameter['required'] ?? false),
      ];
    }

    $body = null;
    $jsonContent = $operation['requestBody']['content']['application/json'] ?? null;

    if (is_array($jsonContent)) {
      $body = [
        'mode' => 'raw',
        'raw' => json_encode($jsonContent['example'] ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'options' => [
          'raw' => [
            'language' => 'json',
          ],
        ],
      ];
    }

    return [
      'name' => $operation['summary'] ?? $operation['operationId'] ?? "$method $path",
      'request' => array_filter([
        'method' => $method,
        'header' => $body ? [['key' => 'Content-Type', 'value' => 'application/json']] : [],
        'body' => $body,
        'url' => [
          'raw' => '{{baseUrl}}' . $resolvedPath,
          'host' => ['{{baseUrl}}'],
          'path' => array_values(array_filter(explode('/', trim($resolvedPath, '/')))),
          'query' => $query,
        ],
        'description' => $operation['summary'] ?? '',
      ], static fn(mixed $value): bool => $value !== null),
      'response' => [],
    ];
  }
}
