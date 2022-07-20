<?php

namespace Assegai\Core\Enumerations\Http;

enum ContextType: string
{
  case HTTP         = 'http';
  case RPC         = 'grpc';
  case GRAPHQL      = 'graphql';
  case WEB_SOCKETS  = 'ws';
}