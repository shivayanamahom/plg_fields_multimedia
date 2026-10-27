<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\Fields\Multimedia\Filter;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFilterInterface;
use Joomla\Plugin\Fields\Multimedia\Helper\MediaHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Фильтр значения поля при сохранении. Браузеру не доверяем: из присланного JSON остаются
 * только существующие файлы внутри images/ с разрешёнными расширениями, не больше max_files.
 *
 * Подключается атрибутом filter="multimedia" (префикс регистрируется в плагине).
 *
 * @since  2.0.0
 */
final class MultimediaFilter implements FormFilterInterface
{
    public function filter(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $settings = MediaHelper::settings([
            'mode'            => (string) $element['mode'],
            'max_files'       => (string) $element['max_files'],
            'file_extensions' => (string) $element['file_extensions'],
        ]);

        $items = [];
        $seen  = [];

        foreach (MediaHelper::parseValue($value) as $item) {
            $ext = MediaHelper::extension($item['file']);

            if (isset($seen[$item['file']]) || !\in_array($ext, $settings['extensions'], true)) {
                continue;
            }

            if (MediaHelper::absolutePath($item['file']) === null) {
                continue;
            }

            $seen[$item['file']] = true;
            $items[]             = $item;

            if (\count($items) >= $settings['max_files']) {
                break;
            }
        }

        return $items ? json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    }
}
