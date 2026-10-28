<?php

/**
 * Вывод значения поля на сайте. Переопределение:
 * templates/<шаблон>/html/plg_fields_multimedia/multimedia.php
 *
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Plugin\Fields\Multimedia\Helper\MediaHelper;

/**
 * @var object                  $field        Поле com_fields; value — JSON со списком.
 * @var \Joomla\Registry\Registry $fieldParams  Параметры плагина и поля.
 */
$settings = MediaHelper::settings($fieldParams);
$items    = [];

foreach (MediaHelper::parseValue($field->value) as $entry) {
	$absolute = MediaHelper::absolutePath($entry['file']);

	// Удалённые из менеджера файлы на страницу не попадают.
	if ($absolute === null || !\in_array(MediaHelper::extension($entry['file']), $settings['extensions'], true)) {
		continue;
	}

	$entry['url'] = MediaHelper::url($entry['file']);

	if ($settings['mode'] === MediaHelper::MODE_GALLERY) {
		$entry['thumb'] = $settings['thumb_width'] > 0
			? MediaHelper::thumbnail($entry['file'], $settings['thumb_width'], max(1, $settings['thumb_height'] ?: $settings['thumb_width']))
			: null;
	} else {
		$entry['ext']  = MediaHelper::extension($entry['file']);
		$entry['name'] = MediaHelper::displayName($entry);
		$entry['size'] = (int) filesize($absolute);
	}

	$items[] = $entry;
}

if (!$items) {
	return;
}

$path     = MediaHelper::layoutPath();
$app      = Factory::getApplication();
$document = method_exists($app, 'getDocument') ? $app->getDocument() : null;

// Вне страницы сайта (запрос API/MCP, консоль) документа нет: разметку выводим, ассеты не подключаем.
$wa = $document ? $document->getWebAssetManager() : null;

if ($wa) {
	$wa->getRegistry()->addExtensionRegistryFile('plg_fields_multimedia');
	$wa->useStyle('plg_fields_multimedia.site');
}

if ($settings['mode'] === MediaHelper::MODE_FILES) {
	echo LayoutHelper::render('plg_fields_multimedia.files', ['items' => $items, 'settings' => $settings], $path);

	return;
}

// Просмотр крупно: WT Fancybox, если плагин подключён, иначе встроенное окно.
$engine = 'none';

if ($settings['lightbox']) {
	$engine = $wa && $wa->assetExists('preset', 'wtfancybox') ? 'fancybox' : 'builtin';

	if ($engine === 'fancybox') {
		$wa->usePreset('wtfancybox');
	}
}

$wa?->useScript('plg_fields_multimedia.site');

echo LayoutHelper::render('plg_fields_multimedia.gallery', [
	'items'    => $items,
	'settings' => $settings,
	'engine'   => $engine,
	'group'    => 'mm-' . (int) ($field->id ?? 0) . '-' . (int) ($item->id ?? 0),
], $path);
