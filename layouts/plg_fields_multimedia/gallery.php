<?php

/**
 * Галерея на сайте: сетка или слайдер. Переопределение:
 * templates/<шаблон>/html/layouts/plg_fields_multimedia/gallery.php
 *
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Plugin\Fields\Multimedia\Helper\MediaHelper;

/**
 * @var array  $displayData
 * @var array  $items     Элементы: file, url, title, alt, thumb (url, width, height или null).
 * @var array  $settings  Проверенные настройки поля.
 * @var string $engine    Просмотр: fancybox (WT Fancybox), builtin или none.
 * @var string $group     Уникальное имя группы для просмотра.
 */
extract($displayData, EXTR_SKIP);

$isSlider = $settings['gallery_layout'] === 'slider';
$path     = MediaHelper::layoutPath();
$classes  = 'multimedia-field mm-gallery mm-cols-' . (int) $settings['columns'] . ($isSlider ? ' mm-gallery--slider' : ' mm-gallery--grid');
?>
<div class="<?php echo $classes; ?>" data-mm-gallery data-mm-engine="<?php echo htmlspecialchars($engine, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isSlider && $settings['slider_autoplay'] > 0 ? ' data-mm-autoplay="' . (int) $settings['slider_autoplay'] . '"' : ''; ?>>
	<?php if ($isSlider) : ?>
		<div class="mm-viewport" data-mm-viewport tabindex="0" role="region" aria-roledescription="carousel" aria-label="<?php echo Text::_('PLG_FIELDS_MULTIMEDIA_GALLERY'); ?>">
	<?php endif; ?>
	<ul class="mm-list">
		<?php foreach ($items as $item) : ?>
			<li class="mm-cell">
				<?php echo LayoutHelper::render('plg_fields_multimedia.image', ['item' => $item, 'settings' => $settings, 'engine' => $engine, 'group' => $group], $path); ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ($isSlider) : ?>
		</div>
		<?php if (\count($items) > 1) : ?>
			<button type="button" class="mm-nav mm-nav--prev" data-mm-prev aria-label="<?php echo Text::_('JPREVIOUS'); ?>"></button>
			<button type="button" class="mm-nav mm-nav--next" data-mm-next aria-label="<?php echo Text::_('JNEXT'); ?>"></button>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php
// Окно просмотра встроенной галереи выводится один раз на страницу.
if ($engine === 'builtin' && MediaHelper::once('lightbox')) {
	echo LayoutHelper::render('plg_fields_multimedia.lightbox', [], $path);
}
