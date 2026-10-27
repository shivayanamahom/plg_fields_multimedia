<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\Fields\Multimedia\Helper;

use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Настройки поля, проверка путей и разбор значения. Единственный источник правды
 * о значениях по умолчанию и допустимых пределах параметров.
 *
 * @since  2.0.0
 */
final class MediaHelper
{
    public const MODE_GALLERY = 'gallery';
    public const MODE_FILES   = 'files';

    /** Подпапка в images/, где лежат миниатюры. */
    public const CACHE_DIR = 'multimedia-cache';

    /** Расширения, которые принимает режим «Галерея». */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** MIME-типы, допустимые для расширений изображений. */
    private const IMAGE_MIMES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
    ];

    /** Ожидаемые MIME-типы для известных документов; для остальных расширений проверяется чёрный список. */
    private const DOCUMENT_MIMES = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/msword', 'application/octet-stream'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip', 'application/octet-stream'],
        'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip', 'application/octet-stream'],
        'odp'  => ['application/vnd.oasis.opendocument.presentation', 'application/zip', 'application/octet-stream'],
        'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'rar'  => ['application/vnd.rar', 'application/x-rar', 'application/x-rar-compressed', 'application/octet-stream'],
        '7z'   => ['application/x-7z-compressed', 'application/octet-stream'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'rtf'  => ['text/rtf', 'application/rtf', 'text/plain'],
    ];

    /** Расширения, которые нельзя разрешить ни в каком режиме (исполняемые и активные файлы). */
    private const DENIED_EXTENSIONS = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc',
        'pl', 'py', 'rb', 'cgi', 'sh', 'bash', 'zsh', 'ps1', 'psm1', 'bat', 'cmd', 'com', 'exe', 'dll', 'msi',
        'scr', 'vbs', 'vbe', 'wsf', 'hta', 'lnk', 'jar', 'war', 'swf', 'asp', 'aspx', 'ashx', 'jsp', 'jspx', 'cfm',
        'js', 'mjs', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'xsl', 'xslt',
        'htaccess', 'htpasswd', 'ini', 'conf', 'config', 'user',
    ];

    /** MIME-типы активного содержимого, которые отклоняются независимо от расширения. */
    private const DENIED_MIMES = [
        'text/html', 'text/x-php', 'application/x-httpd-php', 'application/x-php', 'application/xhtml+xml',
        'image/svg+xml', 'text/javascript', 'application/javascript', 'application/x-javascript', 'text/xml',
        'application/xml', 'application/x-sh', 'application/x-shellscript', 'text/x-shellscript',
        'application/x-dosexec', 'application/x-msdownload', 'application/x-executable', 'application/x-mach-binary',
        'application/java-archive', 'application/x-perl', 'text/x-perl', 'text/x-python', 'application/x-python-code',
    ];

    /** @var array<string, bool> */
    private static array $once = [];

    /**
     * Папка макетов плагина — последний запасной путь для LayoutHelper (переопределения лежат в шаблоне).
     */
    public static function layoutPath(): string
    {
        return \dirname(__DIR__, 2) . '/layouts';
    }

    /**
     * Значения по умолчанию и пределы параметров поля.
     *
     * @return  array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'mode'            => self::MODE_GALLERY,
            'directory'       => 'multimedia',
            'subfolders'      => 'none',
            'max_files'       => 30,
            'max_size'        => 10,
            'delete_files'    => 0,
            'image_resize'    => 'contain',
            'image_width'     => 1920,
            'image_height'    => 1280,
            'image_quality'   => 85,
            'image_webp'      => 0,
            'gallery_layout'  => 'grid',
            'columns'         => 3,
            'thumb_width'     => 600,
            'thumb_height'    => 400,
            'show_captions'   => 0,
            'lightbox'        => 1,
            'slider_autoplay' => 0,
            'file_extensions' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv,zip',
            'files_layout'    => 'list',
            'show_size'       => 1,
            'download'        => 0,
        ];
    }

    /** Параметры, которые имеют смысл только в режиме «Галерея». */
    public static function galleryOnlyParams(): array
    {
        return [
            'spacer_images', 'image_resize', 'image_width', 'image_height', 'image_quality', 'image_webp',
            'spacer_display', 'gallery_layout', 'columns', 'thumb_width', 'thumb_height', 'show_captions',
            'lightbox', 'slider_autoplay',
        ];
    }

    /** Параметры, которые имеют смысл только в режиме «Файлы». */
    public static function filesOnlyParams(): array
    {
        return ['spacer_files', 'file_extensions', 'files_layout', 'show_size', 'download'];
    }

    /**
     * Приводит произвольные параметры поля к проверенному набору: типы, пределы, допустимые значения.
     *
     * @param   Registry|array|string|object|null  $params  Параметры поля (fieldparams).
     *
     * @return  array<string, mixed>
     */
    public static function settings($params): array
    {
        if ($params instanceof Registry) {
            $params = $params->toArray();
        } elseif (\is_string($params)) {
            $params = (array) json_decode($params, true);
        } else {
            $params = (array) $params;
        }

        $d = self::defaults();
        $p = array_merge($d, array_filter($params, static fn ($v) => $v !== '' && $v !== null && !\is_array($v)));

        $mode = $p['mode'] === self::MODE_FILES ? self::MODE_FILES : self::MODE_GALLERY;

        $s = [
            'mode'            => $mode,
            'directory'       => self::sanitizeDirectory((string) $p['directory']),
            'subfolders'      => \in_array($p['subfolders'], ['none', 'month', 'field'], true) ? $p['subfolders'] : 'none',
            'max_files'       => self::clamp($p['max_files'], 1, 200, $d['max_files']),
            'max_size'        => self::clamp($p['max_size'], 1, 200, $d['max_size']),
            'delete_files'    => (int) (bool) (int) $p['delete_files'],
            'image_resize'    => \in_array($p['image_resize'], ['none', 'contain', 'cover'], true) ? $p['image_resize'] : 'contain',
            'image_width'     => self::clamp($p['image_width'], 100, 6000, $d['image_width']),
            'image_height'    => self::clamp($p['image_height'], 100, 6000, $d['image_height']),
            'image_quality'   => self::clamp($p['image_quality'], 40, 100, $d['image_quality']),
            'image_webp'      => (int) (bool) (int) $p['image_webp'],
            'gallery_layout'  => $p['gallery_layout'] === 'slider' ? 'slider' : 'grid',
            'columns'         => self::clamp($p['columns'], 1, 6, $d['columns']),
            'thumb_width'     => self::clamp($p['thumb_width'], 0, 2000, $d['thumb_width']),
            'thumb_height'    => self::clamp($p['thumb_height'], 0, 2000, $d['thumb_height']),
            'show_captions'   => (int) (bool) (int) $p['show_captions'],
            'lightbox'        => (int) (bool) (int) $p['lightbox'],
            'slider_autoplay' => self::clamp($p['slider_autoplay'], 0, 30, 0),
            'files_layout'    => $p['files_layout'] === 'cards' ? 'cards' : 'list',
            'show_size'       => (int) (bool) (int) $p['show_size'],
            'download'        => (int) (bool) (int) $p['download'],
        ];

        $s['file_extensions'] = implode(',', self::parseExtensions((string) $p['file_extensions'], $d['file_extensions']));
        $s['extensions']      = $mode === self::MODE_FILES ? explode(',', $s['file_extensions']) : self::IMAGE_EXTENSIONS;

        return $s;
    }

    /**
     * Разбирает список расширений: нижний регистр, только [a-z0-9], без запрещённых.
     *
     * @return  string[]
     */
    public static function parseExtensions(string $list, string $fallback = ''): array
    {
        $result = [];

        foreach (preg_split('/[\s,;]+/', strtolower($list)) ?: [] as $ext) {
            $ext = ltrim($ext, '.');

            if ($ext !== '' && preg_match('/^[a-z0-9]{1,8}$/', $ext) && !\in_array($ext, self::DENIED_EXTENSIONS, true)) {
                $result[$ext] = $ext;
            }
        }

        if (!$result && $fallback !== '') {
            return self::parseExtensions($fallback);
        }

        return array_values($result);
    }

    public static function isDeniedExtension(string $ext): bool
    {
        return \in_array(strtolower($ext), self::DENIED_EXTENSIONS, true);
    }

    public static function isDeniedMime(string $mime): bool
    {
        return \in_array(strtolower($mime), self::DENIED_MIMES, true);
    }

    /**
     * Допустимые MIME-типы расширения или null, если для него нет точного списка.
     *
     * @return  string[]|null
     */
    public static function expectedMimes(string $ext): ?array
    {
        $ext = strtolower($ext);

        return self::IMAGE_MIMES[$ext] ?? self::DOCUMENT_MIMES[$ext] ?? null;
    }

    /**
     * Папка загрузки внутри images/: только [a-z0-9_-], не более четырёх уровней.
     */
    public static function sanitizeDirectory(string $directory): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', strtolower($directory))) as $part) {
            $part = preg_replace('/[^a-z0-9_-]+/', '', $part) ?? '';

            if ($part !== '') {
                $parts[] = $part;
            }

            if (\count($parts) === 4) {
                break;
            }
        }

        if (!$parts || $parts[0] === self::CACHE_DIR) {
            return 'multimedia';
        }

        return implode('/', $parts);
    }

    /**
     * Проверяет путь файла: относительный, внутри images/, без обходов и служебных имён.
     *
     * @return  string|null  Нормализованный путь или null, если путь недопустим.
     */
    public static function normalizePath($path): ?string
    {
        if (!\is_string($path)) {
            return null;
        }

        // Менеджер медиа добавляет «#joomlaImage://…» и адаптер «local-images:/…».
        $path = trim((string) strtok($path, '#?'));
        $path = preg_replace('#^local-images:/+#', 'images/', $path) ?? '';
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || \strlen($path) > 400 || !str_starts_with($path, 'images/')) {
            return null;
        }

        if (!preg_match('#^[\p{L}\p{N} ._()+,@\-/]+$#u', $path)) {
            return null;
        }

        $segments = explode('/', $path);
        $base     = array_pop($segments);

        foreach (array_merge($segments, [$base]) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || $segment[0] === '.') {
                return null;
            }
        }

        // Служебные папки плагина недоступны как источник файлов.
        if (($segments[1] ?? '') === self::CACHE_DIR) {
            return null;
        }

        // Двойные расширения вроде shell.php.jpg отклоняются целиком.
        $nameParts = explode('.', strtolower($base));
        array_shift($nameParts);

        if (\count($nameParts) < 1) {
            return null;
        }

        foreach ($nameParts as $ext) {
            if (self::isDeniedExtension($ext)) {
                return null;
            }
        }

        return $path;
    }

    /**
     * Разбирает значение поля (JSON-массив или одиночный путь старого формата) в проверенный список.
     *
     * @param   mixed  $value  Значение из базы или из формы.
     *
     * @return  array<int, array{file: string, title: string, alt: string}>
     */
    public static function parseValue($value): array
    {
        if (\is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = [['file' => $value]];
            }

            $value = $decoded;
        }

        // Данные формы приходят из Registry: вложенные массивы становятся объектами.
        if (\is_object($value)) {
            $value = json_decode((string) json_encode($value), true);
        }

        if (!\is_array($value)) {
            return [];
        }

        // Одиночный элемент без внешнего массива.
        if (isset($value['file'])) {
            $value = [$value];
        }

        $items = [];

        foreach ($value as $row) {
            if (\is_string($row)) {
                $row = ['file' => $row];
            }

            if (!\is_array($row)) {
                continue;
            }

            $file = self::normalizePath($row['file'] ?? null);

            if ($file === null) {
                continue;
            }

            $items[] = [
                'file'  => $file,
                'title' => self::cleanText($row['title'] ?? ''),
                'alt'   => self::cleanText($row['alt'] ?? ''),
            ];
        }

        return $items;
    }

    public static function cleanText($text, int $limit = 255): string
    {
        $text = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags((string) $text)) ?? '');

        return mb_substr($text, 0, $limit);
    }

    /**
     * URL файла относительно корня сайта; сегменты кодируются.
     */
    public static function url(string $path): string
    {
        return Uri::root(true) . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Читаемое имя файла для режима «Файлы»: заголовок, иначе имя без расширения.
     */
    public static function displayName(array $item): string
    {
        if ($item['title'] !== '') {
            return $item['title'];
        }

        return pathinfo($item['file'], PATHINFO_FILENAME);
    }

    public static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Эффективный лимит размера файла в байтах с учётом настроек PHP.
     */
    public static function maxBytes(array $settings): int
    {
        $limits = [$settings['max_size'] * 1048576];

        foreach (['upload_max_filesize', 'post_max_size'] as $ini) {
            $bytes = self::iniBytes((string) \ini_get($ini));

            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }

        return (int) min($limits);
    }

    public static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (float) $value;

        switch (strtolower(substr($value, -1))) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
        }

        return (int) $number;
    }

    /**
     * Абсолютный путь к файлу внутри images/ или null, если путь выходит за пределы папки.
     */
    public static function absolutePath(string $path): ?string
    {
        $normalized = self::normalizePath($path);

        if ($normalized === null) {
            return null;
        }

        $root = realpath(JPATH_ROOT . '/images');
        $real = realpath(JPATH_ROOT . '/' . $normalized);

        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }

        return $real;
    }

    /**
     * Возвращает true один раз за запрос для указанного ключа (общая разметка, скрипты).
     */
    public static function once(string $key): bool
    {
        if (isset(self::$once[$key])) {
            return false;
        }

        return self::$once[$key] = true;
    }

    /**
     * Миниатюра изображения: создаётся при первом показе и кэшируется в images/multimedia-cache.
     *
     * @return  array{url: string, width: int, height: int}|null  null — использовать оригинал.
     */
    public static function thumbnail(string $path, int $width, int $height): ?array
    {
        if ($width < 1 || $height < 1 || !ImageProcessor::available()) {
            return null;
        }

        $source = self::absolutePath($path);

        if ($source === null || !\in_array(self::extension($path), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        $webp   = ImageProcessor::supportsWebp();
        $ext    = $webp ? 'webp' : (self::extension($path) === 'png' ? 'png' : 'jpg');
        $rel    = 'images/' . self::CACHE_DIR . '/' . sha1($path) . '/' . $width . 'x' . $height . '-' . filemtime($source) . '.' . $ext;
        $target = JPATH_ROOT . '/' . $rel;

        if (!is_file($target)) {
            // Версии миниатюр для прежней даты изменения оригинала (файл заменили) не копим.
            self::purgeThumbnails($path, (int) filemtime($source));

            try {
                ImageProcessor::thumbnail($source, $target, $width, $height, $ext);
            } catch (\Throwable $e) {
                return null;
            }
        }

        $size = @getimagesize($target);

        return ['url' => self::url($rel), 'width' => $size ? (int) $size[0] : $width, 'height' => $size ? (int) $size[1] : $height];
    }

    /**
     * Удаляет кэшированные миниатюры файла. С $keepMtime остаются только актуальные для этой даты изменения.
     */
    public static function purgeThumbnails(string $path, ?int $keepMtime = null): void
    {
        $dir = JPATH_ROOT . '/images/' . self::CACHE_DIR . '/' . sha1($path);

        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file) && ($keepMtime === null || !str_contains(basename($file), '-' . $keepMtime . '.'))) {
                @unlink($file);
            }
        }

        if ($keepMtime === null) {
            @rmdir($dir);
        }
    }

    /**
     * Ограничивает число диапазоном; нечисловое значение заменяется значением по умолчанию.
     *
     * @param   mixed  $value
     */
    private static function clamp($value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }
}
