<?php

namespace Assegai\Core\Util;

/**
 * The Paths class. Contains utility methods for working with paths.
 *
 * @package Assegai\Core\Util
 */
final class Paths
{
  /**
   * Constructs a new Paths instance.
   */
  private final function __construct()
  {
  }

  /**
   * Returns the directory where the configuration files are stored.
   *
   * @return string The directory where the configuration files are stored.
   */
  public static function getConfigDirectory(): string
  {
    return self::getWorkingDirectory() . '/config';
  }

  /**
   * Returns the current working directory.
   *
   * @return string The current working directory.
   */
  public static function getWorkingDirectory(): string
  {
    return getcwd() ?: '';
  }

  /**
   * Returns the directory where the language files are stored.
   *
   * @return string The directory where the language files are stored.
   */
  public static function getLangDirectory(): string
  {
    return self::getWorkingDirectory() . '/lang';
  }

  /**
   * Returns the directory where the views are stored.
   *
   * @return string The directory where the views are stored.
   */
  public static function getViewDirectory(): string
  {
    return self::join(self::getWorkingDirectory(), 'src/Views');
  }

  /**
   * Joins the given paths.
   *
   * @param string ...$paths The paths to join.
   * @return string The joined path.
   */
  public static function join(string ...$paths): string
  {
    $path = '';

    foreach ($paths as $p) {
      if (is_string($p)) {
        $path .= "/$p";
      }
    }

    $path = preg_replace('/\/+/', '/', $path);
    return rtrim($path, '/');
  }

  /**
   * Returns the directory where the public files are stored.
   *
   * @param string|null $filename The filename to append to the path.
   * @return string The directory where the public files are stored.
   */
  public static function getPublicPath(?string $filename = ''): string
  {
    return self::join(self::getWorkingDirectory(), 'public', $filename);
  }

  /**
   * Returns the mime type of the given file.
   *
   * @param string $filename The filename to get the mime type of.
   * @return string The mime type of the given file.
   */
  public static function getMimeType(string $filename): string
  {
    $mimeType = mime_content_type($filename);
    return match (pathinfo($filename, PATHINFO_EXTENSION)) {
      'js' => 'text/javascript',
      'json' => 'application/json',
      'css' => 'text/css',
      'csv' => 'text/csv',
      'doc' => 'application/msword',
      'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'htm', 'html' => 'text/html',
      'bmp' => 'image/bmp',
      'gif' => 'image/gif',
      'ico' => 'image/vnd.microsoft.icon',
      'jpeg', 'jpg' => 'image/jpeg',
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