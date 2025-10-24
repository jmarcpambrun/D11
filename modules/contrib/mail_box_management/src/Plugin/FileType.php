<?php

namespace Drupal\mail_box_management\Plugin;

/**
 * File FileType.
 *
 * @file
 * FileType contains class FileType.
 */

/**
 * Class FileType responsible for handling attachments icons and base64 prefix.
 *
 * @class
 * FileType responsible for handling attachments icons and base64 prefix.
 */
class FileType {

  /**
   * Array of file icons.
   */
  const FILE_TYPES = [
    // Images.
    'jpeg' => 'ri-image-line',
    'jpg' => 'ri-image-line',
    'png' => 'ri-image-line',
    'gif' => 'ri-image-line',
    'webp' => 'ri-image-line',
    'tiff' => 'ri-image-line',
    'bmp' => 'ri-image-line',
    'svg' => 'ri-image-line',
    'ico' => 'ri-image-line',

    // Document types.
    'pdf' => 'ri-file-pdf-line',
    'doc' => 'ri-file-word-line',
    'docx' => 'ri-file-word-line',
    'xls' => 'ri-file-excel-line',
    'xlsx' => 'ri-file-excel-line',
    'ppt' => 'ri-file-ppt-2-line',
    'pptx' => 'ri-file-ppt-2-line',
  // Open Document Text.
    'odt' => 'ri-file-word-line',
  // Open Document Spreadsheet.
    'ods' => 'ri-file-excel-line',
  // Rich Text Format.
    'rtf' => 'ri-file-text-line',
  // Plain Text.
    'txt' => 'ri-file-text-line',
  // Markdown.
    'md' => 'ri-file-text-line',
  // CSV.
    'csv' => 'ri-file-text-line',

    // Compressed.
    'zip' => 'ri-file-zip-line',
    'rar' => 'ri-file-zip-line',
    'gz' => 'ri-file-zip-line',
    '7z' => 'ri-file-zip-line',
    'tar' => 'ri-file-zip-line',
    'tar.gz' => 'ri-file-zip-line',
    'tar.bz2' => 'ri-file-zip-line',

    // Audio types.
    'mp3' => 'ri-file-music-line',
    'wav' => 'ri-file-music-line',
    'flac' => 'ri-file-music-line',
    'aac' => 'ri-file-music-line',
    'm4a' => 'ri-file-music-line',
    'ogg' => 'ri-file-music-line',
    'wma' => 'ri-file-music-line',

    // Video types.
    'mp4' => 'ri-film-line',
    'avi' => 'ri-film-line',
    'mov' => 'ri-film-line',
    'mkv' => 'ri-film-line',
    'webm' => 'ri-film-line',
    'flv' => 'ri-film-line',
    'wmv' => 'ri-film-line',

    // Other file types.
  // JSON files.
    'json' => 'ri-file-code-line',
  // XML files.
    'xml' => 'ri-file-code-line',
  // HTML files.
    'html' => 'ri-file-code-line',
  // CSS files.
    'css' => 'ri-file-code-line',
  // JavaScript files.
    'js' => 'ri-file-code-line',
  // PHP files.
    'php' => 'ri-file-code-line',
  // Python files.
    'py' => 'ri-file-code-line',
  // Java files.
    'java' => 'ri-file-code-line',
  // C files.
    'c' => 'ri-file-code-line',
  // C++ files.
    'cpp' => 'ri-file-code-line',
  // Header files (C/C++)
    'h' => 'ri-file-code-line',
  // Shell scripts.
    'sh' => 'ri-file-code-line',
  // Batch files.
    'bat' => 'ri-file-code-line',

    // Executable.
  // Executable files.
    'exe' => 'ri-file-settings-line',
  // Android app packages.
    'apk' => 'ri-file-settings-line',
  // Java Archives.
    'jar' => 'ri-file-settings-line',
  ];

  /**
   * Array of file base64 prefix.
   */
  const BASE_64_TYPE = [
    // Images.
    'jpeg' => 'data:image/jpeg;base64,',
    'jpg'  => 'data:image/jpg;base64,',
    'png'  => 'data:image/png;base64,',
    'gif'  => 'data:image/gif;base64,',
    'webp' => 'data:image/webp;base64,',
    'tiff' => 'data:image/tiff;base64,',
    'bmp'  => 'data:image/bmp;base64,',
    'svg'  => 'data:image/svg+xml;base64,',
    'ico'  => 'data:image/x-icon;base64,',

    // Text Files.
    'txt'  => 'data:text/plain;base64,',
    'html' => 'data:text/html;base64,',
    'css'  => 'data:text/css;base64,',
    'csv'  => 'data:text/csv;base64,',
    'js'   => 'data:application/javascript;base64,',
    'json' => 'data:application/json;base64,',
    'xml'  => 'data:application/xml;base64,',
    'yaml' => 'data:application/x-yaml;base64,',
    'md'   => 'data:text/markdown;base64,',

    // PDF.
    'pdf'  => 'data:application/pdf;base64,',

    // Office Documents.
    'doc'  => 'data:application/msword;base64,',
    'docx' => 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,',
    'xls'  => 'data:application/vnd.ms-excel;base64,',
    'xlsx' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,',
    'ppt'  => 'data:application/vnd.ms-powerpoint;base64,',
    'pptx' => 'data:application/vnd.openxmlformats-officedocument.presentationml.presentation;base64,',
    'odt'  => 'data:application/vnd.oasis.opendocument.text;base64,',
    'ods'  => 'data:application/vnd.oasis.opendocument.spreadsheet;base64,',

    // Audio.
    'mp3'  => 'data:audio/mpeg;base64,',
    'wav'  => 'data:audio/wav;base64,',
    'aac'  => 'data:audio/aac;base64,',
    'flac' => 'data:audio/flac;base64,',
    'm4a'  => 'data:audio/x-m4a;base64,',
    'wma'  => 'data:audio/x-ms-wma;base64,',

    // Video.
    'mp4'  => 'data:video/mp4;base64,',
    'webm' => 'data:video/webm;base64,',
    'ogg'  => 'data:video/ogg;base64,',
    'avi'  => 'data:video/x-msvideo;base64,',
    'mov'  => 'data:video/quicktime;base64,',
    'wmv'  => 'data:video/x-ms-wmv;base64,',
    'flv'  => 'data:video/x-flv;base64,',
    'mkv'  => 'data:video/x-matroska;base64,',

    // Compressed Files.
    'zip'  => 'data:application/zip;base64,',
    'rar'  => 'data:application/x-rar-compressed;base64,',
    'tar'  => 'data:application/x-tar;base64,',
    'gz'   => 'data:application/gzip;base64,',
    '7z'   => 'data:application/x-7z-compressed;base64,',
    'xz'   => 'data:application/x-xz;base64,',

    // System Files.
    'exe'  => 'data:application/octet-stream;base64,',
    'bat'  => 'data:application/x-msdownload;base64,',
    'dll'  => 'data:application/x-msdownload;base64,',

    // Disk Images.
    'iso'  => 'data:application/x-iso9660-image;base64,',

    // Fonts.
    'woff'  => 'data:font/woff;base64,',
    'woff2' => 'data:font/woff2;base64,',
    'ttf'   => 'data:font/ttf;base64,',
    'otf'   => 'data:font/otf;base64,',

    // E-Book Formats.
    'epub' => 'data:application/epub+zip;base64,',
    'mobi' => 'data:application/x-mobipocket-ebook;base64,',

    // 3D Model Files
    'obj'  => 'data:application/x-tgif;base64,',
    'stl'  => 'data:application/sla;base64,',
    'fbx'  => 'data:application/x-fbx;base64,',
    'dae'  => 'data:application/xml;base64,',

    // Other Formats.
    'jsonld' => 'data:application/ld+json;base64,',
    'rtf'    => 'data:application/rtf;base64,',
    'ps'     => 'data:application/postscript;base64,',
    'eps'    => 'data:application/postscript;base64,',
    'c'      => 'data:text/x-c;base64,',
    'cpp'    => 'data:text/x-c++;base64,',
    'java'   => 'data:text/x-java;base64,',

    // Miscellaneous File Types.
    'apk'    => 'data:application/vnd.android.package-archive;base64,',
    'ics'    => 'data:text/calendar;base64,',
    'vcf'    => 'data:text/vcard;base64,',
    'torrent' => 'data:application/x-bittorrent;base64,',
    'dat'    => 'data:application/octet-stream;base64,',
  ];

  /**
   * Returns icon class name.
   *
   * @param string $type
   *   File type extension.
   *
   * @return string
   *   Returns icon class name css.
   */
  public static function getIconClass(string $type): string {
    return self::FILE_TYPES[strtolower($type)] ?? 'ri-file-unknow-line';
  }

  /**
   * Get base64 prefix.
   *
   * @param string $type
   *   File type.
   *
   * @return string
   *   Return proper base64 prefix or data:application/octet-stream;base64,
   */
  public static function getBase64Type(string $type): string {
    return self::BASE_64_TYPE[strtolower($type)] ?? 'data:application/octet-stream;base64,';
  }

}
