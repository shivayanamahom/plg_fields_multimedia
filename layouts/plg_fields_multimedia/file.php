<?php

/**
 * Один файл в списке. Переопределение:
 * templates/<шаблон>/html/layouts/plg_fields_multimedia/file.php
 *
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\Plugin\Fields\Multimedia\Helper\MediaHelper;

/**
 * @var array $displayData
 * @var array $item      file, url, name, ext, size.
 * @var array $settings  Настройки поля.
 */
extract($displayData, EXTR_SKIP);

$e    = static fn ($text): string => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$meta = strtoupper($item['ext']) . ($settings['show_size'] && $item['size'] > 0 ? ', ' . MediaHelper::formatSize($item['size']) : '');
?>
<a class="mm-file-link" href="<?php echo $e($item['url']); ?>"<?php echo $settings['download'] ? ' download' : ' target="_blank" rel="noopener"'; ?>>
	<span class="mm-file-icon mm-ext-<?php echo $e($item['ext']); ?>" aria-hidden="true"><?php echo $e(strtoupper($item['ext'])); ?></span>
	<span class="mm-file-name"><?php echo $e($item['name']); ?></span>
	<span class="mm-file-meta"><?php echo $e($meta); ?></span>
</a>
