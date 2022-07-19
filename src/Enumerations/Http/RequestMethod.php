<?php

namespace Assegai\Core\Enumerations\Http;

enum RequestMethod: string
{
  case GET = "GET";
  case POST = "POST";
  case PUT = "PUT";
  case PATCH = "PATCH";
  case DELETE = "DELETE";
  case OPTIONS = "OPTIONS";
  case HEAD = "HEAD";
}