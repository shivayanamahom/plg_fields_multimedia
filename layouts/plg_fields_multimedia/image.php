<?php

/**
 * Одно изображение галереи. Переопределение:
 * templates/<шаблон>/html/layouts/plg_fields_multimedia/image.php
 *
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

/**
 * @var array  $displayData
 * @var array  $item      file, url, title, alt, thumb.
 * @var array  $settings  Настройки поля.
 * @var string $engine    fancybox, builtin или none.
 * @var string $group     Имя группы для просмотра.
 */
extract($displayData, EXTR_SKIP);

$e       = static fn ($text): string => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$src     = $item['thumb']['url'] ?? $item['url'];
$width   = $item['thumb']['width'] ?? null;
$height  = $item['thumb']['height'] ?? null;
// Одно описание служит и alt, и подписью (title — значения, сохранённые прежней версией).
$text    = $item['alt'] !== '' ? $item['alt'] : $item['title'];
$caption = $settings['show_captions'] && $text !== '';

$img = '<img class="mm-img" src="' . $e($src) . '" alt="' . $e($text) . '"'
	. ($width && $height ? ' width="' . (int) $width . '" height="' . (int) $height . '"' : '')
	. ' loading="lazy" decoding="async">';
?>
<figure class="mm-figure">
	<?php if ($engine === 'fancybox') : ?>
		<a class="mm-link" href="<?php echo $e($item['url']); ?>" data-fancybox="<?php echo $e($group); ?>"<?php echo $text !== '' ? ' data-caption="' . $e($text) . '"' : ''; ?>><?php echo $img; ?></a>
	<?php elseif ($engine === 'builtin') : ?>
		<a class="mm-link" href="<?php echo $e($item['url']); ?>" data-mm-open data-mm-group="<?php echo $e($group); ?>"<?php echo $text !== '' ? ' data-mm-caption="' . $e($text) . '"' : ''; ?>><?php echo $img; ?></a>
	<?php else : ?>
		<?php echo $img; ?>
	<?php endif; ?>
	<?php if ($caption) : ?>
		<figcaption class="mm-caption"><?php echo $e($text); ?></figcaption>
	<?php endif; ?>
</figure>
