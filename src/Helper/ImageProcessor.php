<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\Fields\Multimedia\Helper;

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Обработка изображений средствами GD. Пересохранение удаляет из файла всё, кроме пикселей
 * (EXIF с GPS, встроенные скрипты, «хвосты» после данных изображения).
 *
 * @since  2.0.0
 */
final class ImageProcessor
{
    /** Предел размера исходного изображения, пикселей. */
    public const MAX_PIXELS = 64000000;

    /** Предел стороны исходного изображения, пикселей. */
    public const MAX_SIDE = 12000;

    public static function available(): bool
    {
        return \extension_loaded('gd') && \function_exists('imagecreatetruecolor');
    }

    public static function supportsWebp(): bool
    {
        return self::available() && \function_exists('imagewebp') && \function_exists('imagecreatefromwebp');
    }

    /**
     * Размеры и тип файла по содержимому. Бросает исключение для не-изображений и слишком больших картинок.
     *
     * @return  array{0: int, 1: int, 2: int}  Ширина, высота, константа IMAGETYPE_*.
     */
    public static function inspect(string $file): array
    {
        $info = @getimagesize($file);

        if ($info === false || empty($info[0]) || empty($info[1])) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_NOT_IMAGE'));
        }

        if ($info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_IMAGE_TOO_BIG'));
        }

        return [(int) $info[0], (int) $info[1], (int) $info[2]];
    }

    /**
     * Анимированный GIF сохраняется как есть: GD оставил бы один кадр.
     */
    public static function isAnimatedGif(string $file): bool
    {
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return false;
        }

        $frames = 0;
        $chunk  = '';

        while (!feof($handle) && $frames < 2) {
            $data = fread($handle, 102400);

            if ($data === false || $data === '') {
                break;
            }

            $frames += preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk . $data);
            $chunk   = substr($data, -16);
        }

        fclose($handle);

        return $frames > 1;
    }

    /**
     * Читает изображение, учитывая ориентацию EXIF.
     */
    public static function open(string $file, int $type): \GdImage
    {
        self::assertMemory($file);

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG  => @imagecreatefrompng($file),
            IMAGETYPE_GIF  => @imagecreatefromgif($file),
            IMAGETYPE_WEBP => self::supportsWebp() ? @imagecreatefromwebp($file) : false,
            default        => false,
        };

        if (!$image instanceof \GdImage) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_NOT_IMAGE'));
        }

        if ($type === IMAGETYPE_JPEG && \function_exists('exif_read_data')) {
            $exif        = @exif_read_data($file);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            $angle       = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);

                if ($rotated instanceof \GdImage) {
                    $image = $rotated;
                }
            }
        }

        return $image;
    }

    /**
     * Изменяет размер: contain — вписать в рамку без увеличения, cover — обрезать до точных размеров.
     */
    public static function resize(\GdImage $image, int $width, int $height, string $mode): \GdImage
    {
        $sw = imagesx($image);
        $sh = imagesy($image);

        if ($mode === 'cover') {
            $ratio = $width / $height;
            $cw    = $sw;
            $ch    = $sh;

            if ($sw / $sh > $ratio) {
                $cw = (int) round($sh * $ratio);
            } else {
                $ch = (int) round($sw / $ratio);
            }

            $cx = (int) floor(($sw - $cw) / 2);
            $cy = (int) floor(($sh - $ch) / 2);

            // Без увеличения: если источник меньше рамки, остаётся его родной масштаб.
            $dw = min($width, $cw);
            $dh = min($height, $ch);

            return self::copy($image, $dw, $dh, $cx, $cy, $cw, $ch);
        }

        if ($mode === 'contain') {
            $scale = min($width / $sw, $height / $sh, 1);

            if ($scale >= 1) {
                return $image;
            }

            return self::copy($image, max(1, (int) round($sw * $scale)), max(1, (int) round($sh * $scale)), 0, 0, $sw, $sh);
        }

        return $image;
    }

    /**
     * Сохраняет изображение в указанном формате (jpg, png, webp, gif).
     */
    public static function save(\GdImage $image, string $file, string $format, int $quality): void
    {
        $quality = max(40, min(100, $quality));

        $ok = match ($format) {
            'png'  => imagepng($image, $file, 6),
            'webp' => imagewebp($image, $file, $quality),
            'gif'  => imagegif($image, $file),
            default => self::saveJpeg($image, $file, $quality),
        };

        if (!$ok) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_SAVE'));
        }
    }

    /**
     * Миниатюра с обрезкой по центру до точных размеров. Запись атомарная (через временный файл).
     */
    public static function thumbnail(string $source, string $target, int $width, int $height, string $format): void
    {
        [, , $type] = self::inspect($source);

        $image = self::open($source, $type);
        $thumb = self::resize($image, $width, $height, 'cover');

        $dir = \dirname($target);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new UploadException('mkdir');
        }

        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';

        try {
            self::save($thumb, $tmp, $format, 82);

            if (!@rename($tmp, $target)) {
                throw new UploadException('rename');
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private static function saveJpeg(\GdImage $image, string $file, int $quality): bool
    {
        // JPEG не поддерживает прозрачность: подкладываем белый фон.
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imageinterlace($flat, true);

        return imagejpeg($flat, $file, $quality);
    }

    private static function copy(\GdImage $image, int $dw, int $dh, int $sx, int $sy, int $sw, int $sh): \GdImage
    {
        $dst = imagecreatetruecolor($dw, $dh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, true);
        imagecopyresampled($dst, $image, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);

        return $dst;
    }

    /**
     * Оценивает, хватит ли памяти для декодирования, чтобы не уронить запрос фатальной ошибкой.
     */
    private static function assertMemory(string $file): void
    {
        $info  = @getimagesize($file);
        $limit = MediaHelper::iniBytes((string) \ini_get('memory_limit'));

        if (!$info || $limit <= 0) {
            return;
        }

        // Декодированное изображение ≈ 4–5 байт на пиксель, плюс копия при изменении размера.
        $need = (int) ($info[0] * $info[1] * 5 * 1.8);

        if ($need + memory_get_usage(true) > $limit) {
            throw new UploadException(Text::_('PLG_FIELDS_MULTIMEDIA_ERR_IMAGE_TOO_BIG'));
        }
    }
}
