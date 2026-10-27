<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\Fields\Multimedia\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Ошибка загрузки, текст которой можно безопасно показать пользователю (уже переведён).
 *
 * @since  2.0.0
 */
final class UploadException extends \RuntimeException
{
}
