<?php

/**
 * Список файлов на сайте. Переопределение:
 * templates/<шаблон>/html/layouts/plg_fields_multimedia/files.php
 *
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Plugin\Fields\Multimedia\Helper\MediaHelper;

/**
 * @var array $displayData
 * @var array $items     Элементы: file, url, title, name, ext, size (байты или 0).
 * @var array $settings  Проверенные настройки поля.
 */
extract($displayData, EXTR_SKIP);

$path = MediaHelper::layoutPath();
?>
<ul class="multimedia-field mm-files mm-files--<?php echo $settings['files_layout'] === 'cards' ? 'cards' : 'list'; ?>">
	<?php foreach ($items as $item) : ?>
		<li class="mm-file">
			<?php echo LayoutHelper::render('plg_fields_multimedia.file', ['item' => $item, 'settings' => $settings], $path); ?>
		</li>
	<?php endforeach; ?>
</ul>
