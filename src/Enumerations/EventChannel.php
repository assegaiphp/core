<?php

namespace Assegai\Core\Enumerations;

enum EventChannel: string
{
  case APP_INIT_START = 'app-init-start';
  case APP_INIT_FINISH = 'app-init-finish';
  case APP_LISTENING_START = 'app-listening-start';
  case APP_LISTENING_FINISH = 'app-listening-finish';
  case APP_SHUTDOWN_START = 'app-shutdown-start';
  case APP_SHUTDOWN_FINISH = 'app-shutdown-finish';
  case MODULE_RESOLUTION_START = 'module-resolution-start';
  case MODULE_RESOLUTION_FINISH = 'module-resolution-finish';
  case PROVIDER_RESOLUTION_START = 'provider-resolution-start';
  case PROVIDER_RESOLUTION_FINISH = 'provider-resolution-finish';
  case CONTROLLER_RESOLUTION_START = 'controller-resolution-start';
  case CONTROLLER_RESOLUTION_FINISH = 'controller-resolution-finish';
  case CONTROLLER_WILL_ACTIVATE = 'controller-will-activate';
  case CONTROLLER_DID_ACTIVATE = 'controller-did-activate';
  case HANDLER_WILL_ACTIVATE = 'handler-will-activate';
  case HANDLER_DID_ACTIVATE = 'handler-did-activate';
  case REQUEST_HANDLING_START = 'request-handling-start';
  case REQUEST_HANDLING_FINISH = 'request-handling-finish';
  case RESPONSE_HANDLING_START = 'response-handling-start';
  case RESPONSE_HANDLING_FINISH = 'response-handling-finish';
}