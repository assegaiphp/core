<?php

namespace Assegai\Core\Util;

/**
 *
 */
final class Paths
{
  /**
   *
   */
  private final function __construct()
  {}

  /**
   * @return string
   */
  public static function getWorkingDirectory(): string
  {
    return trim(shell_exec("pwd"));
  }

  /**
   * @return string
   */
  public static function getConfigDirectory(): string
  {
    return self::getWorkingDirectory() . '/config';
  }

  /**
   * @return string
   */
  public static function getViewDirectory(): string
  {
    return self::join(self::getWorkingDirectory(), 'views');
  }

  /**
   * @param string|null $filename
   * @return string
   */
  public static function getPublicPath(?string $filename = ''): string
  {
    return self::join(self::getWorkingDirectory(), 'public', $filename);
  }

  /**
   * @param ...$paths
   * @return string
   */
  public static function join(...$paths): string
  {
    $path = '';

    foreach ($paths as $p)
    {
      if (is_string($p))
      {
        $path .= "/$p";
      }
    }

    $path = preg_replace('/\/+/', '/', $path);
    return rtrim($path, '/');
  }

  /**
   * @param string $filename
   * @return string
   */
  public static function getMimeType(string $filename): string
  {
    $mimeType = mime_content_type($filename);
    return match(pathinfo($filename, PATHINFO_EXTENSION)) {
      'js' => 'text/javascript',
      'json' => 'application/json',
      'css' => 'text/css',
      'csv' => 'text/csv',
      'doc' => 'application/msword',
      'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'htm',
      'html' => 'text/html',
      'bmp' => 'image/bmp',
      'gif' => 'image/gif',
      'ico' => 'image/vnd.microsoft.icon',
      'jpeg',
      'jpg' => 'image/jpeg',
      'mp3' => 'audio/mpeg',
      'mp4' => 'audio/mp4',
      'mpeg' => 'video/mpeg',
      'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
      'odt' => 'application/vnd.oasis.opendocument.text',
      'oga' => 'audio/ogg',
      'ogv' => 'video/ogg',
      'ogx' => 'application/ogg',
      'png' => 'image/png',
      'pdf' => 'application/pdf',
      'php' => 'application/x-httpd-php',
      'rtf' => 'application/rtf',
      'ts' => 'video/mp2t',
      'ttf' => 'font/ttf',
      'txt' => 'text/plain',
      'wav' => 'audio/wav',
      'weba', 'webm' => 'audio/webm',
      'webp' => 'audio/webp',
      'woff' => 'font/woff',
      'woff2' => 'font/woff2',
      'xhtml' => 'application/xhtml+xml',
      'xls' => 'application/vnd.ms-excel',
      'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'xml' => 'application/xml',
      '7z' => 'application/x-7z-compressed',
      default => $mimeType,
    };
  }
}