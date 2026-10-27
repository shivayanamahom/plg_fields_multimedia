<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\Fields\Multimedia\Helper;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Приём одного загруженного файла. Все настройки приходят от сервера (из параметров поля),
 * от браузера берётся только сам файл.
 *
 * @since  2.0.0
 */
final class UploadHandler
{
    /**
     * @param   bool  $strictUpload  Требовать, чтобы файл пришёл через HTTP-загрузку (false — только для тестов).
     */
    public function __construct(private readonly bool $strictUpload = true)
    {
    }

    /**
     * @param   array   $settings   Проверенные настройки поля (MediaHelper::settings()).
     * @param   array   $upload     Элемент массива $_FILES.
     * @param   string  $fieldName  Имя поля — для режима подпапок «по полю».
     *
     * @return  array{file: string, name: string, size: int}
     *
     * @throws  UploadException
     */
    public function handle(array $settings, array $upload, string $fieldName = ''): array
    {
        $tmp = $this->validateUpload($upload, $settings);

        $original = (string) $upload['name'];
        $ext      = $this->detectExtension($original, $settings);
        $mime     = $this->detectMime($tmp);

        if (MediaHelper::isDeniedMime($mime)) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_TYPE'));
        }

        $directory = $this->prepareDirectory($settings, $fieldName);
        $baseName  = $this->baseName($original);

        if ($settings['mode'] === MediaHelper::MODE_GALLERY) {
            return $this->storeImage($settings, $tmp, $mime, $ext, $directory, $baseName);
        }

        return $this->storeFile($tmp, $mime, $ext, $directory, $baseName);
    }

    /**
     * @return  string  Путь к временному файлу.
     */
    private function validateUpload(array $upload, array $settings): string
    {
        foreach (['name', 'tmp_name', 'error', 'size'] as $key) {
            if (!isset($upload[$key]) || \is_array($upload[$key])) {
                throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_NO_FILE'));
            }
        }

        $error = (int) $upload['error'];

        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => Text::sprintf('PLG_FIELDS_MULTIMEDIA_ERR_SIZE', MediaHelper::formatSize(MediaHelper::maxBytes($settings))),
                UPLOAD_ERR_NO_FILE                        => Text::_('PLG_FIELDS_MULTIMEDIA_ERR_NO_FILE'),
                default                                   => Text::_('PLG_FIELDS_MULTIMEDIA_ERR_UPLOAD'),
            });
        }

        $tmp = (string) $upload['tmp_name'];

        if ($tmp === '' || !is_file($tmp) || ($this->strictUpload && !is_uploaded_file($tmp))) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_UPLOAD'));
        }

        $size = (int) filesize($tmp);
        $max  = MediaHelper::maxBytes($settings);

        if ($size < 1) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_EMPTY'));
        }

        if ($size > $max) {
            throw new UploadException(Text::sprintf('PLG_FIELDS_MULTIMEDIA_ERR_SIZE', MediaHelper::formatSize($max)));
        }

        return $tmp;
    }

    /**
     * Расширение из имени файла: разрешено настройками поля и не входит в чёрный список.
     */
    private function detectExtension(string $name, array $settings): string
    {
        $name = str_replace("\0", '', basename(str_replace('\\', '/', $name)));
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($ext === '' || MediaHelper::isDeniedExtension($ext) || !\in_array($ext, $settings['extensions'], true)) {
            throw new UploadException(Text::sprintf('PLG_FIELDS_MULTIMEDIA_ERR_EXTENSION', implode(', ', $settings['extensions'])));
        }

        // Двойные расширения (shell.php.jpg) — признак подмены.
        $parts = explode('.', strtolower($name));
        array_shift($parts);
        array_pop($parts);

        foreach ($parts as $part) {
            if (MediaHelper::isDeniedExtension($part)) {
                throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_TYPE'));
            }
        }

        return $ext;
    }

    private function detectMime(string $file): string
    {
        if (!\class_exists(\finfo::class)) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_SERVER'));
        }

        return strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($file));
    }

    /**
     * Безопасное имя файла (латиница, цифры, дефис) из исходного.
     */
    private function baseName(string $original): string
    {
        $name = pathinfo(str_replace('\\', '/', $original), PATHINFO_FILENAME);
        $slug = OutputFilter::stringURLSafe($name);
        $slug = trim(preg_replace('/[^a-z0-9_-]+/', '-', strtolower($slug)) ?? '', '-_');

        if ($slug === '') {
            $slug = 'file-' . bin2hex(random_bytes(3));
        }

        return substr($slug, 0, 80);
    }

    /**
     * Создаёт папку загрузки (с защитой от выполнения скриптов) и возвращает пару «относительный, абсолютный» путь.
     *
     * @return  array{0: string, 1: string}
     */
    private function prepareDirectory(array $settings, string $fieldName): array
    {
        $relative = 'images/' . $settings['directory'];

        $protect = JPATH_ROOT . '/' . $relative;
        $this->ensureDirectory($protect);
        $this->writeProtection($protect);

        if ($settings['subfolders'] === 'month') {
            $relative .= '/' . gmdate('Y') . '/' . gmdate('m');
        } elseif ($settings['subfolders'] === 'field') {
            $field = preg_replace('/[^a-z0-9_-]+/', '', strtolower($fieldName)) ?? '';

            if ($field !== '') {
                $relative .= '/' . $field;
            }
        }

        $absolute = JPATH_ROOT . '/' . $relative;
        $this->ensureDirectory($absolute);

        // Итоговая папка должна физически находиться внутри images/ (защита от символических ссылок).
        $images = realpath(JPATH_ROOT . '/images');
        $real   = realpath($absolute);

        if ($images === false || $real === false || ($real !== $images && !str_starts_with($real, $images . DIRECTORY_SEPARATOR))) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_SERVER'));
        }

        return [$relative, $real];
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_SERVER'));
        }
    }

    /**
     * Запрещает выполнение скриптов в папке загрузок (Apache и IIS). Существующие файлы не перезаписываются.
     */
    private function writeProtection(string $directory): void
    {
        $htaccess = <<<'HTACCESS'
# plg_fields_multimedia: в этой папке скрипты не выполняются.
<IfModule mod_php.c>
	php_flag engine off
</IfModule>
<IfModule mod_php7.c>
	php_flag engine off
</IfModule>
<IfModule mod_php8.c>
	php_flag engine off
</IfModule>
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pht .phps
<FilesMatch "(?i)(^\.ht|\.(php[0-9]?|phtml|pht|phps|phar|pl|py|cgi|sh|asp|aspx|jsp|html?|shtml|svgz?|js|xml)$)">
	<IfModule mod_authz_core.c>
		Require all denied
	</IfModule>
	<IfModule !mod_authz_core.c>
		Order allow,deny
		Deny from all
	</IfModule>
</FilesMatch>

HTACCESS;

        $webConfig = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
	<system.webServer>
		<handlers>
			<clear />
			<add name="StaticFile" path="*" verb="*" modules="StaticFileModule" resourceType="Either" requireAccess="Read" />
		</handlers>
		<security>
			<requestFiltering>
				<fileExtensions>
					<add fileExtension=".php" allowed="false" />
					<add fileExtension=".phtml" allowed="false" />
					<add fileExtension=".phar" allowed="false" />
					<add fileExtension=".asp" allowed="false" />
					<add fileExtension=".aspx" allowed="false" />
				</fileExtensions>
			</requestFiltering>
		</security>
	</system.webServer>
</configuration>

XML;

        foreach (['.htaccess' => $htaccess, 'web.config' => $webConfig] as $name => $content) {
            if (!is_file($directory . '/' . $name)) {
                @file_put_contents($directory . '/' . $name, $content);
            }
        }
    }

    /**
     * @param   array{0: string, 1: string}  $directory
     */
    private function storeImage(array $settings, string $tmp, string $mime, string $ext, array $directory, string $baseName): array
    {
        $allowed = MediaHelper::expectedMimes($ext) ?? [];

        if (!\in_array($mime, $allowed, true)) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_NOT_IMAGE'));
        }

        [, , $type] = ImageProcessor::inspect($tmp);

        $typeExt = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$type] ?? '';
        $extNorm = $ext === 'jpeg' ? 'jpg' : $ext;

        // Содержимое должно соответствовать расширению.
        if ($typeExt === '' || $typeExt !== $extNorm) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_NOT_IMAGE'));
        }

        if (!ImageProcessor::available()) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_GD'));
        }

        $work = $tmp;
        $out  = $extNorm;
        $temp = null;

        if ($type === IMAGETYPE_GIF && ImageProcessor::isAnimatedGif($tmp)) {
            // Анимация сохраняется как есть.
            $final = $tmp;
        } else {
            $image = ImageProcessor::open($tmp, $type);

            if ($settings['image_resize'] !== 'none') {
                $image = ImageProcessor::resize($image, $settings['image_width'], $settings['image_height'], $settings['image_resize']);
            }

            if ($settings['image_webp'] && ImageProcessor::supportsWebp() && $type !== IMAGETYPE_GIF) {
                $out = 'webp';
            }

            $temp = tempnam(sys_get_temp_dir(), 'mm_');

            if ($temp === false) {
                throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_SERVER'));
            }

            try {
                ImageProcessor::save($image, $temp, $out, $settings['image_quality']);
            } catch (\Throwable $e) {
                @unlink($temp);

                throw $e;
            }

            $final = $temp;
        }

        try {
            return $this->place($final, $directory, $baseName, $out);
        } finally {
            if ($temp !== null && is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    /**
     * @param   array{0: string, 1: string}  $directory
     */
    private function storeFile(string $tmp, string $mime, string $ext, array $directory, string $baseName): array
    {
        $expected = MediaHelper::expectedMimes($ext);

        if ($expected !== null && !\in_array($mime, $expected, true)) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_TYPE'));
        }

        // В документе не должно быть серверного кода.
        $head = (string) file_get_contents($tmp, false, null, 0, 4096);

        if (preg_match('/<\?(php|=)/i', $head)) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_TYPE'));
        }

        return $this->place($tmp, $directory, $baseName, $ext);
    }

    /**
     * Копирует файл в папку назначения под свободным именем. Имя резервируется атомарно (режим «x»).
     *
     * @param   array{0: string, 1: string}  $directory
     */
    private function place(string $source, array $directory, string $baseName, string $ext): array
    {
        [$relative, $absolute] = $directory;

        $name   = $baseName . '.' . $ext;
        $target = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $handle = @fopen($absolute . '/' . $name, 'xb');

            if ($handle !== false) {
                $target = $handle;
                break;
            }

            $name = $baseName . '-' . bin2hex(random_bytes(2 + $attempt)) . '.' . $ext;
        }

        if ($target === null) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_SERVER'));
        }

        $in = fopen($source, 'rb');

        if ($in === false || stream_copy_to_stream($in, $target) === false) {
            fclose($target);
            @unlink($absolute . '/' . $name);

            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_SERVER'));
        }

        fclose($in);
        fclose($target);
        @chmod($absolute . '/' . $name, 0644);

        return ['file' => $relative . '/' . $name, 'name' => $name, 'size' => (int) filesize($absolute . '/' . $name)];
    }
}
