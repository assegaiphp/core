<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Http\Requests\Request;
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

    $requestBody = Request::getInstance()->getBody();
    $key = $this->fieldName;
    $file = $requestBody->$key;
    $file['target_dir'] = $options->dest;
    $file['target_path'] = $options->dest . '/' . $file['name'];
    $file['extension'] = strtolower(pathinfo($file['target_path'], PATHINFO_EXTENSION));

    Request::getInstance()->setFile($file);

    return null;
  }
}