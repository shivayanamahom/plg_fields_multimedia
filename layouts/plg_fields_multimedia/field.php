<?php

/**
 * Поле ввода: обёртка над стандартным повторяемым subform Joomla с загрузкой с компьютера.
 * Переопределение: templates/<шаблон>/html/layouts/plg_fields_multimedia/field.php
 *
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * @var array  $displayData
 * @var string $subform   Готовый HTML повторяемого subform (штатные поля ядра).
 * @var array  $settings  Проверенные настройки поля.
 * @var array  $config    Параметры скрипта загрузки (передаются в data-config).
 */
extract($displayData, EXTR_SKIP);

$hint = Text::sprintf(
	$settings['mode'] === 'gallery' ? 'PLG_FIELDS_MULTIMEDIA_FIELD_HINT_GALLERY' : 'PLG_FIELDS_MULTIMEDIA_FIELD_HINT_FILES',
	implode(', ', $config['extensions']),
	$settings['max_files'],
	round($config['maxBytes'] / 1048576, 1)
);
?>
<div class="multimedia-field mm-field" data-mm-field data-config="<?php echo htmlspecialchars(json_encode($config, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
	<div class="mm-toolbar">
		<label class="btn btn-primary mm-upload">
			<span class="icon-upload" aria-hidden="true"></span> <?php echo Text::_('PLG_FIELDS_MULTIMEDIA_FIELD_UPLOAD'); ?>
			<input type="file" class="visually-hidden" data-mm-input<?php echo $config['maxFiles'] > 1 ? ' multiple' : ''; ?> accept="<?php echo htmlspecialchars('.' . implode(',.', $config['extensions']), ENT_QUOTES, 'UTF-8'); ?>">
		</label>
		<span class="form-text"><?php echo htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></span>
	</div>
	<div class="mm-drop" data-mm-drop hidden><?php echo Text::_('PLG_FIELDS_MULTIMEDIA_FIELD_DROP'); ?></div>
	<p class="mm-status" role="status" aria-live="polite" data-mm-status></p>
	<?php echo $subform; ?>
</div>
