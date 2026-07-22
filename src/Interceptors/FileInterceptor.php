<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Exceptions\Http\BadRequestException;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Interfaces\IAssegaiInterceptor;

/**
 * An interceptor that intercepts file uploads.
 *
 * @package Assegai\Core\Interceptors
 */
#[Injectable]
readonly class FileInterceptor implements IAssegaiInterceptor
{
  /**
   * FileInterceptor constructor.
   *
   * @param string $fieldName The name of the field in the request body that contains the file.
   * @param FileInterceptorOptions|null $options The options for the file interceptor.
   */
  public function __construct(
    public string                  $fieldName,
    public ?FileInterceptorOptions $options = null
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function intercept(ExecutionContext $context, ?FileInterceptorOptions $options = null): ?callable
  {
    if (!$options) {
      $options = $this->options ?? new FileInterceptorOptions();
    }

    $request = $context->switchToHttp()->getRequest();
    $key = $this->fieldName;
    $uploadedFiles = $request->getFile();
    $candidate = is_array($uploadedFiles)
      ? ($uploadedFiles[$key] ?? $uploadedFiles)
      : ($uploadedFiles->$key ?? $uploadedFiles);

    if (is_object($candidate)) {
      $candidate = get_object_vars($candidate);
    }

    if (!is_array($candidate) || !isset($candidate['name'], $candidate['tmp_name'])) {
      throw new BadRequestException("Missing uploaded file [$key].");
    }

    $error = (int)($candidate['error'] ?? UPLOAD_ERR_OK);

    if ($error !== UPLOAD_ERR_OK) {
      throw new BadRequestException("Upload [$key] failed with error code $error.");
    }

    $tmpName = (string)$candidate['tmp_name'];
    $actualSize = $tmpName !== '' && is_file($tmpName) ? filesize($tmpName) : false;
    $size = max(0, is_int($actualSize) ? $actualSize : (int)($candidate['size'] ?? 0));
    $maxFileSize = $options->limits['fileSize'] ?? $options->limits['size'] ?? null;

    if (is_numeric($maxFileSize) && $size > (int)$maxFileSize) {
      throw new BadRequestException("Upload [$key] exceeds the configured file size limit.");
    }

    $originalName = str_replace('\\', '/', (string)$candidate['name']);
    $safeName = basename($originalName);

    if ($safeName === '' || $safeName === '.' || $safeName === '..' || str_starts_with($safeName, '.')) {
      throw new BadRequestException("Upload [$key] has an unsafe filename.");
    }

    $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

    if (preg_match('/^(?:php\d*|phtml|phar|phps|inc|cgi|pl|py|rb|sh|bash|exe|com|bat|cmd|msi|htaccess)$/i', $extension)) {
      throw new BadRequestException("Upload [$key] uses a prohibited executable extension.");
    }

    $detectedType = null;

    if ($tmpName !== '' && is_file($tmpName)) {
      $detectedType = mime_content_type($tmpName);
    }

    $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? ".{$extension}" : '');
    $targetDirectory = rtrim($options->dest, "/\\");

    if ($targetDirectory === '' || str_contains($targetDirectory, "\0")) {
      throw new BadRequestException('The upload destination is invalid.');
    }

    $file = [
      ...$candidate,
      'name' => $safeName,
      'original_name' => $safeName,
      'stored_name' => $storedName,
      'type' => is_string($detectedType) ? $detectedType : (string)($candidate['type'] ?? ''),
      'size' => $size,
      'target_dir' => $targetDirectory,
      'target_path' => $targetDirectory . DIRECTORY_SEPARATOR . $storedName,
      'extension' => $extension,
    ];

    if ($options->fileFilter && ($options->fileFilter)($file, $request) !== true) {
      throw new BadRequestException("Upload [$key] was rejected by the configured file filter.");
    }

    $request->setFile($file);

    return null;
  }
}
