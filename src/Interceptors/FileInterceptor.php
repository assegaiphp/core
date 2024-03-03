<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Interfaces\IAssegaiInterceptor;

#[Injectable]
readonly class FileInterceptor implements IAssegaiInterceptor
{
  public function __construct(
    public string                  $fieldName,
    public ?FileInterceptorOptions $options = null
  )
  {
  }

  public function intercept(ExecutionContext $context, ?FileInterceptorOptions $options = null): ?callable
  {
    // TODO: Implement intercept() method.
    if (!$options)
    {
      $options = new FileInterceptorOptions();
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