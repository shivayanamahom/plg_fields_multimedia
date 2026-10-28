<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.multimedia
 *
 * @copyright   (C) 2026 Domanov Maxim (web design dharma). All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Plugin\Fields\Multimedia\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\SubformField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Plugin\Fields\Multimedia\Helper\MediaHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Поле ввода значения. Основа — стандартный повторяемый subform Joomla: в каждой строке
 * штатное поле «Медиа» (выбор файла, предпросмотр, загрузка в менеджере медиа), подпись и alt;
 * порядок меняется перетаскиванием, строки добавляются и удаляются кнопками ядра.
 * Сверху — необязательная надстройка: загрузка с компьютера с обработкой на сервере.
 *
 * @since  2.0.0
 */
class MultimediaField extends SubformField
{
    protected $type = 'Multimedia';

    private const STRINGS = [
        'PLG_FIELDS_MULTIMEDIA_JS_UPLOADING',
        'PLG_FIELDS_MULTIMEDIA_JS_UPLOADED',
        'PLG_FIELDS_MULTIMEDIA_JS_LIMIT',
        'PLG_FIELDS_MULTIMEDIA_JS_TOO_BIG',
        'PLG_FIELDS_MULTIMEDIA_JS_BAD_TYPE',
        'PLG_FIELDS_MULTIMEDIA_JS_ERROR',
    ];

    /** @var array<string, mixed> */
    private array $settings = [];

    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        if (!parent::setup($element, $value, $group)) {
            return false;
        }

        $this->settings = MediaHelper::settings([
            'mode'            => (string) $element['mode'],
            'directory'       => (string) $element['directory'],
            'max_files'       => (string) $element['max_files'],
            'max_size'        => (string) $element['max_size'],
            'file_extensions' => (string) $element['file_extensions'],
        ]);

        // В форму попадают только допустимые значения: недопустимые убираются при отображении, а не при сохранении.
        $this->value = array_values(array_filter(
            MediaHelper::parseValue($this->value),
            fn (array $item): bool => \in_array(MediaHelper::extension($item['file']), $this->settings['extensions'], true)
        ));

        // Настройки повторяемого subform задаёт плагин, а не XML поля.
        $this->multiple = true;
        $this->__set('min', 0);
        $this->__set('max', $this->settings['max_files']);
        $this->__set('layout', 'joomla.form.field.subform.repeatable');
        $this->__set('buttons', 'add,remove,move');
        $this->__set('groupByFieldset', 'false');
        $this->__set('formsource', $this->itemFormXml());

        return true;
    }

    /**
     * Поля одной строки. Для файлов выбор ограничен документами, для галереи — изображениями.
     */
    private function itemFormXml(): string
    {
        $gallery = $this->settings['mode'] === MediaHelper::MODE_GALLERY;
        $directory = htmlspecialchars($this->settings['directory'], ENT_QUOTES, 'UTF-8');

        $xml = '<form>'
            . '<field name="file" type="media" required="true" hiddenLabel="true" types="' . ($gallery ? 'images' : 'documents') . '"'
            . ' directory="' . $directory . '" preview="' . ($gallery ? 'tooltip' : 'false') . '"'
            . ' label="' . ($gallery ? 'PLG_FIELDS_MULTIMEDIA_FIELD_IMAGE' : 'PLG_FIELDS_MULTIMEDIA_FIELD_FILE') . '"/>';

        // Галерея: одно описание служит и alt, и подписью. Файлы: название для посетителей.
        $xml .= $gallery
            ? '<field name="alt" type="text" maxlength="255" hiddenLabel="true" label="PLG_FIELDS_MULTIMEDIA_FIELD_ALT" hint="PLG_FIELDS_MULTIMEDIA_FIELD_ALT"/>'
            : '<field name="title" type="text" maxlength="255" hiddenLabel="true" label="PLG_FIELDS_MULTIMEDIA_FIELD_NAME" hint="PLG_FIELDS_MULTIMEDIA_FIELD_NAME"/>';

        return $xml . '</form>';
    }

    protected function getLayoutPaths()
    {
        $paths   = parent::getLayoutPaths();
        $paths[] = MediaHelper::layoutPath();

        return $paths;
    }

    protected function getInput()
    {
        $subform = parent::getInput();

        // Загрузка с компьютера доступна, если поле сохранено и разрешено текущему пользователю.
        $fieldId   = (int) $this->element['field_id'];
        $canUpload = $fieldId > 0 && !$this->disabled && !$this->readonly;

        if (!$canUpload) {
            return $subform;
        }

        $app      = Factory::getApplication();
        $document = method_exists($app, 'getDocument') ? $app->getDocument() : null;

        // Без документа страницы (запрос API/MCP, консоль) скрипты и стили подключать некуда.
        if ($document === null) {
            return $subform;
        }

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('plg_fields_multimedia');
        $wa->useStyle('plg_fields_multimedia.field')->useScript('plg_fields_multimedia.field');

        foreach (self::STRINGS as $key) {
            Text::script($key);
        }

        $config = [
            'mode'       => $this->settings['mode'],
            'maxFiles'   => $this->settings['max_files'],
            'maxBytes'   => MediaHelper::maxBytes($this->settings),
            'extensions' => $this->settings['extensions'],
            'fieldId'    => $fieldId,
            'uploadUrl'  => Uri::base(true) . '/index.php?option=com_ajax&plugin=multimedia&group=fields&format=json',
            'token'      => Session::getFormToken(),
        ];

        return LayoutHelper::render('plg_fields_multimedia.field', [
            'subform'  => $subform,
            'settings' => $this->settings,
            'config'   => $config,
        ], MediaHelper::layoutPath());
    }
}
