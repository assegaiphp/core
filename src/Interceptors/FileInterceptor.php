<?php

namespace Assegai\Core\Interceptors;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\ExecutionContext;
use Assegai\Core\Http\Requests\Request;
use Assegai\Core\Interfaces\IAssegaiInterceptor;

#[Injectable]
class FileInterceptor implements IAssegaiInterceptor
{
  public function __construct(
    public readonly string $fieldName,
    public readonly ?FileInterceptorOptions $options = null
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
    $file['target_path'] = $options->dest . '/' . $file['name'];

    Request::getInstance()->setFile($file);

    return null;
  }
}