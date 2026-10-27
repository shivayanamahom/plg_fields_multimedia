<?php

/**
 * Окно просмотра встроенной галереи (используется, если WT Fancybox не подключён).
 * Переопределение: templates/<шаблон>/html/layouts/plg_fields_multimedia/lightbox.php
 *
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<dialog class="mm-lightbox" data-mm-lightbox aria-label="<?php echo Text::_('PLG_FIELDS_MULTIMEDIA_GALLERY'); ?>">
	<button type="button" class="mm-lb-btn mm-lb-close" data-mm-lb-close aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
	<button type="button" class="mm-lb-btn mm-lb-prev" data-mm-lb-prev aria-label="<?php echo Text::_('JPREVIOUS'); ?>"></button>
	<figure class="mm-lb-figure">
		<img class="mm-lb-img" src="" alt="" data-mm-lb-img>
		<figcaption class="mm-lb-caption" data-mm-lb-caption></figcaption>
	</figure>
	<button type="button" class="mm-lb-btn mm-lb-next" data-mm-lb-next aria-label="<?php echo Text::_('JNEXT'); ?>"></button>
</dialog>
